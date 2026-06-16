<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Event;
use App\Models\AppointmentType;
use App\Models\Plan;
use App\Models\Resource;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    private function getStripe(): StripeClient
    {
        // Prefer DB-stored key (admin settings) → fall back to .env
        $secret = Setting::getValue('pg_stripe_secret')
                ?: config('services.stripe.secret', '');

        if (empty($secret)) {
            abort(503, 'Stripe is not configured. Please add your Stripe Secret Key in Admin → Settings → Payment Gateways.');
        }

        return new StripeClient($secret);
    }

    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.type'                => ['required', 'in:course,event,appointment,appointment_type,resource,plan'],
            'items.*.id'                  => ['required', 'integer'],
            'items.*.quantity'            => ['sometimes', 'integer', 'min:1'],
            'items.*.scheduled_at'        => ['sometimes', 'nullable', 'date'],
            'items.*.consent_data'        => ['sometimes', 'nullable', 'array'],
            'items.*.consented_at'        => ['sometimes', 'nullable', 'date'],
            'items.*.booking_data'        => ['sometimes', 'nullable', 'array'],
            'success_url'                 => ['sometimes', 'url'],
            'cancel_url'                  => ['sometimes', 'url'],
        ]);

        $user        = $request->user();
        $lineItems   = [];
        $orderItems  = [];

        foreach ($request->items as $item) {
            $qty = $item['quantity'] ?? 1;

            if ($item['type'] === 'course') {
                $course = Course::findOrFail($item['id']);
                if ($course->type === 'free' || (float) $course->price === 0.0) {
                    return response()->json(['message' => "'{$course->title}' is a free course — use the Enroll for Free button instead."], 422);
                }
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => $course->title],
                        'unit_amount'  => (int) ($course->price * 100),
                    ],
                    'quantity' => 1,
                ];
                $orderItems[] = ['model' => $course, 'qty' => 1, 'name' => $course->title, 'price' => $course->price];
            } elseif ($item['type'] === 'event') {
                $event = Event::findOrFail($item['id']);
                if ($event->isSoldOut()) {
                    return response()->json(['message' => "Event '{$event->title}' is sold out."], 422);
                }
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => $event->title . ' - Ticket'],
                        'unit_amount'  => (int) ($event->price * 100),
                    ],
                    'quantity' => $qty,
                ];
                $orderItems[] = ['model' => $event, 'qty' => $qty, 'name' => $event->title, 'price' => $event->price];
            } elseif ($item['type'] === 'resource') {
                $resource = Resource::findOrFail($item['id']);
                if ($resource->is_free) {
                    return response()->json(['message' => "'{$resource->title}' is a free resource and does not need to be purchased."], 422);
                }
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => $resource->title],
                        'unit_amount'  => (int) ($resource->price * 100),
                    ],
                    'quantity' => 1,
                ];
                $orderItems[] = ['model' => $resource, 'qty' => 1, 'name' => $resource->title, 'price' => $resource->price];
            } elseif ($item['type'] === 'appointment' || $item['type'] === 'appointment_type') {
                $type = AppointmentType::findOrFail($item['id']);
                if (!$type->is_active) {
                    return response()->json(['message' => "Appointment type '{$type->title}' is not currently available."], 422);
                }
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => $type->title],
                        'unit_amount'  => (int) ($type->price * 100),
                    ],
                    'quantity' => 1,
                ];
                $meta = array_filter([
                    'scheduled_at' => $item['scheduled_at'] ?? null,
                    'consent_data' => $item['consent_data'] ?? null,
                    'consented_at' => $item['consented_at'] ?? null,
                    'booking_data' => $item['booking_data'] ?? null,
                ], fn($v) => $v !== null);
                $orderItems[] = ['model' => $type, 'qty' => 1, 'name' => $type->title, 'price' => $type->price, 'meta' => $meta ?: null];
            } elseif ($item['type'] === 'plan') {
                $plan = Plan::withoutCompanyScope()->findOrFail($item['id']);
                if (!$plan->is_active) {
                    return response()->json(['message' => "Plan '{$plan->name}' is no longer available."], 422);
                }
                $lineItems[] = [
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => ['name' => $plan->name],
                        'unit_amount'  => (int) ($plan->price * 100),
                    ],
                    'quantity' => 1,
                ];
                $orderItems[] = ['model' => $plan, 'qty' => 1, 'name' => $plan->name, 'price' => $plan->price];
            }
        }

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'user_id'      => $user->id,
            'subtotal'     => collect($orderItems)->sum(fn ($i) => $i['price'] * $i['qty']),
            'tax'          => 0,
            'total'        => collect($orderItems)->sum(fn ($i) => $i['price'] * $i['qty']),
            'status'       => 'pending',
            'payment_method' => 'stripe',
        ]);

        foreach ($orderItems as $item) {
            $order->items()->create([
                'purchasable_type' => get_class($item['model']),
                'purchasable_id'   => $item['model']->id,
                'name'             => $item['name'],
                'price'            => $item['price'],
                'quantity'         => $item['qty'],
                'subtotal'         => $item['price'] * $item['qty'],
                'meta'             => $item['meta'] ?? null,
            ]);
        }

        $session = $this->getStripe()->checkout->sessions->create([
            'customer_email' => $user->email,
            'payment_method_types' => ['card'],
            'line_items'    => $lineItems,
            'mode'          => 'payment',
            'success_url'   => $request->success_url ?? config('app.frontend_url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'    => $request->cancel_url  ?? config('app.frontend_url') . '/checkout/cancel',
            'metadata'      => ['order_id' => $order->id, 'user_id' => $user->id],
        ]);

        $order->update(['stripe_session_id' => $session->id]);

        return response()->json(['url' => $session->url, 'checkout_url' => $session->url, 'order_id' => $order->id]);
    }

    public function success(Request $request): JsonResponse
    {
        $sessionId = $request->session_id;
        if (!$sessionId) {
            return response()->json(['message' => 'Missing session ID.'], 422);
        }

        $order = Order::withoutCompanyScope()->where('stripe_session_id', $sessionId)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Already fulfilled — just return
        if ($order->status === 'paid') {
            return response()->json(['message' => 'Payment confirmed.', 'order' => $order]);
        }

        // Verify with Stripe that payment actually succeeded
        try {
            $session = $this->getStripe()->checkout->sessions->retrieve($sessionId);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not verify payment with Stripe.'], 502);
        }

        if ($session->payment_status !== 'paid') {
            return response()->json(['message' => 'Payment not completed.'], 402);
        }

        // Set tenant context for emails / scoped models
        if ($order->company_id) {
            $company = \App\Models\Company::find($order->company_id);
            app(\App\Services\TenantContext::class)->setCompany($company);
        }

        $order->update([
            'status'                => 'paid',
            'stripe_payment_intent' => $session->payment_intent,
            'paid_at'               => now(),
        ]);

        // Fulfill each item (same logic as webhook)
        $order->loadMissing('items');
        foreach ($order->items as $item) {
            app(\App\Http\Controllers\Api\WebhookController::class)->fulfillItem($item, $order->user_id);
        }

        // Send order confirmation email
        $order->loadMissing(['user', 'items']);
        if ($order->user) {
            $frontendUrl = config('app.frontend_url', url('/'));
            $itemNames   = $order->items->pluck('name')->filter()->implode(', ');
            \App\Services\EmailService::send($order->user->email, 'order_confirmed', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{item_name}'     => $itemNames,
                '{order_total}'   => '$' . number_format($order->total, 2),
                '{order_date}'    => now()->format('F j, Y'),
                '{dashboard_url}' => $frontendUrl . '/dashboard',
            ]);
        }

        $this->sendAdminOrderNotification($order);

        return response()->json(['message' => 'Payment confirmed.', 'order' => $order]);
    }

    public function freeCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.type'     => ['required', 'in:course,event,resource'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user       = $request->user();
        $orderItems = [];

        foreach ($request->items as $item) {
            $qty = $item['quantity'] ?? 1;

            if ($item['type'] === 'event') {
                $event = Event::findOrFail($item['id']);
                if ((float) $event->price !== 0.0) {
                    return response()->json(['message' => "'{$event->title}' is not a free event."], 422);
                }
                if ($event->isSoldOut()) {
                    return response()->json(['message' => "'{$event->title}' is sold out."], 422);
                }
                // Check if already registered
                $alreadyHas = \App\Models\OrderItem::whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', 'paid'))
                    ->where('purchasable_type', Event::class)
                    ->where('purchasable_id', $event->id)
                    ->exists();
                if ($alreadyHas) {
                    return response()->json(['message' => 'You are already registered for this event.'], 422);
                }
                $orderItems[] = ['model' => $event, 'qty' => $qty, 'name' => $event->title, 'price' => 0];
            } elseif ($item['type'] === 'course') {
                $course = Course::findOrFail($item['id']);
                if ((float) $course->price !== 0.0 && $course->type !== 'free') {
                    return response()->json(['message' => "'{$course->title}' is not free."], 422);
                }
                $orderItems[] = ['model' => $course, 'qty' => 1, 'name' => $course->title, 'price' => 0];
            } elseif ($item['type'] === 'resource') {
                $resource = Resource::findOrFail($item['id']);
                if (!$resource->is_free && (float) $resource->price !== 0.0) {
                    return response()->json(['message' => "'{$resource->title}' is not free."], 422);
                }
                $orderItems[] = ['model' => $resource, 'qty' => 1, 'name' => $resource->title, 'price' => 0];
            }
        }

        $order = Order::create([
            'order_number'   => 'ORD-' . strtoupper(uniqid()),
            'user_id'        => $user->id,
            'subtotal'       => 0,
            'tax'            => 0,
            'total'          => 0,
            'status'         => 'paid',
            'payment_method' => 'free',
            'paid_at'        => now(),
        ]);

        foreach ($orderItems as $item) {
            $order->items()->create([
                'purchasable_type' => get_class($item['model']),
                'purchasable_id'   => $item['model']->id,
                'name'             => $item['name'],
                'price'            => 0,
                'quantity'         => $item['qty'],
                'subtotal'         => 0,
            ]);
        }

        // Set tenant context for emails
        if ($order->company_id) {
            $company = \App\Models\Company::find($order->company_id);
            app(\App\Services\TenantContext::class)->setCompany($company);
        }

        // Fulfill each item (creates tickets, enrollments, etc.)
        $order->loadMissing('items');
        foreach ($order->items as $orderItem) {
            app(WebhookController::class)->fulfillItem($orderItem, $user->id);
        }

        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $order->loadMissing('items');
        $itemNames   = $order->items->pluck('name')->filter()->implode(', ');
        \App\Services\EmailService::send($user->email, 'order_confirmed', [
            '{username}'      => $user->name,
            '{email}'         => $user->email,
            '{order_id}'      => (string) $order->id,
            '{item_name}'     => $itemNames,
            '{order_total}'   => 'Free',
            '{order_date}'    => now()->format('F j, Y'),
            '{dashboard_url}' => $frontendUrl . '/dashboard',
        ]);

        $this->sendAdminOrderNotification($order);

        return response()->json(['message' => 'Registration confirmed.', 'order' => $order->load('items')], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Payment cancelled.']);
    }

    private function sendAdminOrderNotification(Order $order): void
    {
        // Admin email: settings override → fall back to the global super-admin account
        $adminEmail = Setting::getValue('admin_notification_email')
                   ?? User::withoutGlobalScopes()->whereNull('company_id')->value('email');

        if (!$adminEmail) return;

        $order->loadMissing(['user', 'items']);
        $itemNames   = $order->items->pluck('name')->implode(', ');
        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $adminUrl    = rtrim(config('app.admin_url', $frontendUrl), '/');

        \App\Services\EmailService::send($adminEmail, 'admin_new_order', [
            '{order_id}'        => (string) $order->id,
            '{customer_name}'   => $order->user?->name ?? 'Unknown',
            '{customer_email}'  => $order->user?->email ?? '',
            '{item_name}'       => $itemNames,
            '{order_total}'     => $order->total == 0 ? 'Free' : '$' . number_format($order->total, 2),
            '{order_date}'      => now()->format('F j, Y g:i A'),
            '{admin_url}'       => $adminUrl . '/admin/orders',
            '{site_name}'       => Setting::getValue('site_name', config('app.name')),
        ]);
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.type'         => ['required', 'in:course,event,appointment,appointment_type,resource,plan'],
            'items.*.id'           => ['required', 'integer'],
            'items.*.quantity'     => ['sometimes', 'integer', 'min:1'],
            'items.*.scheduled_at' => ['sometimes', 'nullable', 'date'],
            'items.*.consent_data' => ['sometimes', 'nullable', 'array'],
            'items.*.consented_at' => ['sometimes', 'nullable', 'date'],
            'items.*.booking_data' => ['sometimes', 'nullable', 'array'],
            'billing'              => ['sometimes', 'array'],
        ]);

        $user        = $request->user();
        $orderItems  = [];

        foreach ($request->items as $item) {
            $qty = $item['quantity'] ?? 1;
            if ($item['type'] === 'course') {
                $model = Course::findOrFail($item['id']);
                if ($model->type === 'free' || (float) $model->price === 0.0) {
                    return response()->json(['message' => "'{$model->title}' is free — use Enroll for Free instead."], 422);
                }
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price];
            } elseif ($item['type'] === 'event') {
                $model = Event::findOrFail($item['id']);
                if ($model->isSoldOut()) return response()->json(['message' => "Event '{$model->title}' is sold out."], 422);
                $orderItems[] = ['model' => $model, 'qty' => $qty, 'name' => $model->title, 'price' => $model->price];
            } elseif ($item['type'] === 'resource') {
                $model = Resource::findOrFail($item['id']);
                if ($model->is_free) return response()->json(['message' => "'{$model->title}' is free."], 422);
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price];
            } elseif ($item['type'] === 'appointment' || $item['type'] === 'appointment_type') {
                $model = AppointmentType::findOrFail($item['id']);
                if (!$model->is_active) return response()->json(['message' => "'{$model->title}' is not available."], 422);
                $meta = array_filter([
                    'scheduled_at' => $item['scheduled_at'] ?? null,
                    'consent_data' => $item['consent_data'] ?? null,
                    'consented_at' => $item['consented_at'] ?? null,
                    'booking_data' => $item['booking_data'] ?? null,
                ], fn($v) => $v !== null);
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->title, 'price' => $model->price, 'meta' => $meta ?: null];
            } elseif ($item['type'] === 'plan') {
                $model = Plan::withoutCompanyScope()->findOrFail($item['id']);
                if (!$model->is_active) return response()->json(['message' => "Plan '{$model->name}' is not available."], 422);
                $orderItems[] = ['model' => $model, 'qty' => 1, 'name' => $model->name, 'price' => $model->price];
            }
        }

        $total = collect($orderItems)->sum(fn($i) => $i['price'] * $i['qty']);

        $order = Order::create([
            'order_number'   => 'ORD-' . strtoupper(uniqid()),
            'user_id'        => $user->id,
            'subtotal'       => $total,
            'tax'            => 0,
            'total'          => $total,
            'status'         => 'pending',
            'payment_method' => 'stripe_direct',
        ]);

        foreach ($orderItems as $item) {
            $order->items()->create([
                'purchasable_type' => get_class($item['model']),
                'purchasable_id'   => $item['model']->id,
                'name'             => $item['name'],
                'price'            => $item['price'],
                'quantity'         => $item['qty'],
                'subtotal'         => $item['price'] * $item['qty'],
                'meta'             => $item['meta'] ?? null,
            ]);
        }

        $paymentIntent = $this->getStripe()->paymentIntents->create([
            'amount'                    => (int) ($total * 100),
            'currency'                  => 'usd',
            'receipt_email'             => $user->email,
            'metadata'                  => ['order_id' => $order->id, 'user_id' => $user->id],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        $order->update(['stripe_payment_intent' => $paymentIntent->id]);

        return response()->json([
            'client_secret' => $paymentIntent->client_secret,
            'order_id'      => $order->id,
        ]);
    }

    public function confirmPaymentIntent(Request $request): JsonResponse
    {
        $request->validate(['payment_intent_id' => ['required', 'string']]);

        $order = Order::where('stripe_payment_intent', $request->payment_intent_id)->firstOrFail();

        if ($order->status === 'paid') {
            return response()->json(['message' => 'Payment confirmed.', 'order' => $order]);
        }

        try {
            $pi = $this->getStripe()->paymentIntents->retrieve($request->payment_intent_id);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not verify payment with Stripe.'], 502);
        }

        if ($pi->status !== 'succeeded') {
            return response()->json(['message' => 'Payment not completed.'], 402);
        }

        if ($order->company_id) {
            $company = \App\Models\Company::find($order->company_id);
            app(\App\Services\TenantContext::class)->setCompany($company);
        }

        $order->update(['status' => 'paid', 'paid_at' => now()]);

        $order->loadMissing('items');
        foreach ($order->items as $item) {
            app(WebhookController::class)->fulfillItem($item, $order->user_id);
        }

        $order->loadMissing(['user', 'items']);
        if ($order->user) {
            $frontendUrl = config('app.frontend_url', url('/'));
            $itemNames   = $order->items->pluck('name')->filter()->implode(', ');
            \App\Services\EmailService::send($order->user->email, 'order_confirmed', [
                '{username}'      => $order->user->name,
                '{email}'         => $order->user->email,
                '{order_id}'      => (string) $order->id,
                '{item_name}'     => $itemNames,
                '{order_total}'   => '$' . number_format($order->total, 2),
                '{order_date}'    => now()->format('F j, Y'),
                '{dashboard_url}' => $frontendUrl . '/dashboard',
            ]);
        }

        $this->sendAdminOrderNotification($order);

        return response()->json(['message' => 'Payment confirmed.', 'order' => $order]);
    }

    public function billingPortal(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_customer_id) {
            return response()->json(['message' => 'No billing account found.'], 404);
        }

        $session = $this->getStripe()->billingPortal->sessions->create([
            'customer'   => $user->stripe_customer_id,
            'return_url' => config('app.frontend_url') . '/dashboard',
        ]);

        return response()->json(['url' => $session->url]);
    }
}