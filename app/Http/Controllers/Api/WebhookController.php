<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Resource;
use App\Models\UserPlanSubscription;
use App\Services\EmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function handleStripe(Request $request): Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = \App\Models\Setting::getValue('pg_stripe_webhook_secret')
                     ?: config('services.stripe.webhook_secret');

        if (empty($secret)) {
            \Illuminate\Support\Facades\Log::warning('Stripe webhook received but webhook secret is not configured.');
            return response('Webhook secret not configured.', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature.', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded'  => $this->handleInvoicePaid($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionCancelled($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        // Stripe webhooks arrive without a domain context — resolve tenant from the order
        $order = Order::withoutCompanyScope()->where('stripe_session_id', $session->id)->first();
        if (!$order || $order->status === 'paid') return;

        // Set tenant context so all subsequent queries/emails use the correct company scope
        if ($order->company_id) {
            $company = \App\Models\Company::find($order->company_id);
            app(\App\Services\TenantContext::class)->setCompany($company);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $session) {
            $order->update([
                'status'                => 'paid',
                'stripe_payment_intent' => $session->payment_intent,
                'paid_at'               => now(),
            ]);

            foreach ($order->items as $item) {
                $this->fulfillItem($item, $order->user_id);
            }
        });

        // Send order confirmation email outside the transaction (non-critical path)
        $order->loadMissing('user');
        if ($order->user) {
            $frontendUrl = config('app.frontend_url', url('/'));
            EmailService::send($order->user->email, 'order_confirmed', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{order_total}'   => '$' . number_format($order->total / 100, 2),
                '{order_date}'    => now()->format('F j, Y'),
                '{dashboard_url}' => $frontendUrl . '/dashboard',
            ]);
        }
    }

    public function fulfillItem(OrderItem $item, int $userId): void
    {
        if ($item->purchasable_type === Plan::class) {
            $plan = Plan::withoutCompanyScope()->with('items')->find($item->purchasable_id);
            if (!$plan) return;

            $expiresAt = now()->add($plan->interval === 'month'
                ? \Carbon\CarbonInterval::months($plan->interval_count)
                : \Carbon\CarbonInterval::years($plan->interval_count));

            UserPlanSubscription::create([
                'user_id'       => $userId,
                'plan_id'       => $plan->id,
                'order_item_id' => $item->id,
                'company_id'    => $plan->company_id,
                'started_at'    => now(),
                'expires_at'    => $expiresAt,
                'status'        => 'active',
            ]);

            $user = \App\Models\User::find($userId);
            if ($user) {
                $company     = app(\App\Services\TenantContext::class)->getCompany();
                $scheme      = app()->environment('local') ? 'http' : 'https';
                $frontendUrl = $company ? rtrim($scheme . '://' . $company->domain, '/') : rtrim(config('app.frontend_url'), '/');
                \App\Services\EmailService::send($user->email, 'subscription_started', [
                    '{username}'          => $user->name,
                    '{email}'             => $user->email,
                    '{plan_name}'         => $plan->name,
                    '{next_billing_date}' => Carbon::parse($expiresAt)->format('F j, Y'),
                    '{dashboard_url}'     => $frontendUrl . '/dashboard',
                    '{site_name}'         => \App\Models\Setting::getValue('site_name', config('app.name')),
                ]);
            }
            return;
        } elseif ($item->purchasable_type === Resource::class) {
            // Access is granted by the paid order item itself (checked in ResourceController::show).
            // Nothing else to provision — the order item record is the entitlement.
            return;
        } elseif ($item->purchasable_type === Course::class) {
            $enrollment = Enrollment::updateOrCreate(
                ['user_id' => $userId, 'course_id' => $item->purchasable_id],
                ['type' => 'purchased', 'order_item_id' => $item->id]
            );

            if ($enrollment->wasRecentlyCreated) {
                $user   = \App\Models\User::find($userId);
                $course = \App\Models\Course::find($item->purchasable_id);
                if ($user && $course) {
                    $company     = app(\App\Services\TenantContext::class)->getCompany();
                    $scheme      = app()->environment('local') ? 'http' : 'https';
                    $frontendUrl = $company ? rtrim($scheme . '://' . $company->domain, '/') : rtrim(config('app.frontend_url'), '/');
                    \App\Services\EmailService::send($user->email, 'enrollment_confirmed', [
                        '{username}'      => $user->name,
                        '{email}'         => $user->email,
                        '{course_title}'  => $course->title,
                        '{course_url}'    => $frontendUrl . '/courses/' . $course->slug,
                        '{dashboard_url}' => $frontendUrl . '/dashboard',
                        '{site_name}'     => \App\Models\Setting::getValue('site_name', config('app.name')),
                    ]);
                }
            }
        } elseif ($item->purchasable_type === Event::class) {
            $event = Event::find($item->purchasable_id);
            if ($event) {
                $ticket = EventTicket::create([
                    'user_id'       => $userId,
                    'event_id'      => $event->id,
                    'order_item_id' => $item->id,
                    'ticket_code'   => strtoupper(Str::random(12)),
                    'quantity'      => $item->quantity,
                    'status'        => 'active',
                ]);
                $event->increment('tickets_sold', $item->quantity);

                $user = \App\Models\User::find($userId);
                if ($user) {
                    \App\Services\EmailService::send($user->email, 'event_ticket', [
                        '{username}'      => $user->name,
                        '{email}'         => $user->email,
                        '{event_title}'   => $event->title,
                        '{event_date}'    => $event->starts_at
                            ? Carbon::parse($event->starts_at)->format('l, F j Y \a\t g:i A') . ' (' . (last(explode('/', $event->timezone ?? 'UTC'))) . ')'
                            : 'TBA',
                        '{ticket_number}' => $ticket->ticket_code,
                        '{site_name}'     => \App\Models\Setting::getValue('site_name', config('app.name')),
                    ]);
                }
            }
        } elseif ($item->purchasable_type === AppointmentType::class) {
            $type = AppointmentType::find($item->purchasable_id);
            if (!$type) return;

            $meta         = $item->meta ?? [];
            $scheduledAt  = $meta['scheduled_at'] ?? null;
            $consentData  = $meta['consent_data'] ?? null;
            $consentedAt  = $meta['consented_at'] ?? null;
            $bookingData  = $meta['booking_data'] ?? null;

            // Only create the appointment if a scheduled_at was captured
            if (!$scheduledAt) return;

            // Save mandatory booking fields to the user's profile
            if (!empty($bookingData)) {
                $allowed = ['phone', 'date_of_birth', 'time_of_birth', 'place_of_birth'];
                $updates = array_intersect_key($bookingData, array_flip($allowed));
                if (!empty($updates)) {
                    \App\Models\User::where('id', $userId)->update($updates);
                }
            }

            $appt = Appointment::create([
                'user_id'              => $userId,
                'appointment_type_id'  => $type->id,
                'order_item_id'        => $item->id,
                'scheduled_at'         => $scheduledAt,
                'duration_minutes'     => $type->duration_minutes,
                'status'               => 'pending',
                'consent_data'         => $consentData,
                'consented_at'         => $consentedAt,
                'company_id'           => $type->company_id,
                'timezone'             => \App\Models\Setting::getValue('timezone') ?? config('app.timezone'),
            ]);

            // Generate consent PDF if consent data is present
            if ($consentData && $consentedAt && $type->consent_template_id) {
                try {
                    $user     = $appt->user()->first();
                    $template = $type->consentTemplate;

                    if ($template && $user) {
                        $fields     = $template->fields ?? [];
                        $answers    = $consentData;
                        $disclaimer = $template->disclaimer_html
                            ? strip_tags(html_entity_decode($template->disclaimer_html, ENT_QUOTES, 'UTF-8'))
                            : null;

                        $fieldData = [];
                        foreach ($fields as $field) {
                            $fieldData[] = [
                                'id'       => $field['id'] ?? '',
                                'type'     => $field['type'] ?? 'text',
                                'label'    => $field['label'] ?? '',
                                'required' => $field['required'] ?? false,
                                'answer'   => $answers[$field['id']] ?? null,
                            ];
                        }

                        $company = \App\Models\Company::find($type->company_id);

                        $pdf = Pdf::loadView('appointments.consent-pdf', [
                            'appointmentId'   => $appt->id,
                            'templateName'    => $template->name,
                            'userName'        => $user->name,
                            'userEmail'       => $user->email,
                            'appointmentType' => $type->title,
                            'scheduledAt'     => Carbon::parse($scheduledAt)->setTimezone($appt->timezone ?? config('app.timezone'))->format('l, F j Y \a\t g:i A'),
                            'disclaimer'      => $disclaimer,
                            'fields'          => $fieldData,
                            'consentedAt'     => Carbon::parse($consentedAt)->format('l, F j Y \a\t g:i A \U\T\C'),
                            'generatedAt'     => Carbon::now()->format('F j, Y \a\t g:i A'),
                            'companyName'     => $company->name ?? config('app.name'),
                            'companyLogo'     => $company->logo ?? null,
                        ])->setPaper('a4', 'portrait');

                        $path = 'consents/appointment_' . $appt->id . '.pdf';
                        Storage::disk('local')->put($path, $pdf->output());
                        $appt->update(['consent_pdf_path' => $path]);
                    }
                } catch (\Throwable $e) {
                    \Log::error('Consent PDF (webhook) failed for appointment ' . $appt->id . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function handleInvoicePaid(object $invoice): void
    {
        // Only process recurring subscription renewals, not initial checkout invoices
        if (($invoice->billing_reason ?? '') !== 'subscription_cycle') {
            return;
        }

        $stripeCustomer = $invoice->customer ?? null;
        if (!$stripeCustomer) return;

        // Webhook has no company context — BelongsToCompany scope is bypassed (isGlobal)
        $user = \App\Models\User::where('stripe_customer_id', $stripeCustomer)->first();
        if (!$user) return;

        $sub = UserPlanSubscription::withoutCompanyScope()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        if (!$sub?->plan) return;

        $plan      = $sub->plan;
        $extension = $plan->interval === 'month'
            ? \Carbon\CarbonInterval::months($plan->interval_count)
            : \Carbon\CarbonInterval::years($plan->interval_count);

        $sub->update(['expires_at' => ($sub->expires_at ?? now())->add($extension)]);
    }

    private function handleSubscriptionCancelled(object $subscription): void
    {
        // Find our subscription record via order items that reference this Stripe subscription
        // We look up by matching stripe_session_id on orders created around the same customer
        $stripeCustomer = $subscription->customer ?? null;
        if (!$stripeCustomer) return;

        // Find the most recent active subscription for this Stripe customer via orders
        $order = Order::withoutCompanyScope()
            ->whereNotNull('stripe_payment_intent')
            ->with('user')
            ->whereHas('user', function ($q) use ($stripeCustomer) {
                $q->where('stripe_customer_id', $stripeCustomer);
            })
            ->latest()
            ->first();

        $user = $order?->user;
        if (!$user) return;

        // Mark the subscription as cancelled
        $sub = UserPlanSubscription::withoutCompanyScope()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        if ($sub) {
            $sub->update(['status' => 'cancelled']);

            // Set tenant context for correct branding
            if ($sub->company_id) {
                $company = \App\Models\Company::find($sub->company_id);
                if ($company) app(\App\Services\TenantContext::class)->setCompany($company);
            }

            $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
            EmailService::send($user->email, 'subscription_cancelled', [
                '{username}'      => $user->name,
                '{email}'         => $user->email,
                '{plan_name}'     => $sub->plan?->title ?? 'Subscription',
                '{dashboard_url}' => $frontendUrl . '/dashboard',
                '{site_name}'     => AppModelsSetting::getValue('site_name', config('app.name')),
            ]);
        }
    }
}