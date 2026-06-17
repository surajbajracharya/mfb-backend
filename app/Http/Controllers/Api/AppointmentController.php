<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\ConsentTemplate;
use App\Models\Order;
use App\Services\EmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = $request->user()->appointments()->with("type")->latest("scheduled_at")->get();
        return response()->json(["data" => $appointments]);
    }
    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($request->user()->appointments()->with("type")->findOrFail($id));
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "appointment_type_id" => ["required", "exists:appointment_types,id"],
            "scheduled_at" => ["required", "date", "after:now"],
            "notes" => ["nullable", "string"],
            "consent_data" => ["nullable", "array"],
            "consented_at" => ["nullable", "date"],
            "booking_data" => ["nullable", "array"],
        ]);
        $type = AppointmentType::findOrFail($data["appointment_type_id"]);
        // Store mandatory field data onto the user's profile
        if (!empty($data['booking_data'])) {
            $allowed = ['phone', 'date_of_birth', 'time_of_birth', 'place_of_birth'];
            $updates = array_intersect_key($data['booking_data'], array_flip($allowed));
            if (!empty($updates)) {
                $request->user()->fill($updates)->save();
            }
        }
        if ($type->consent_template_id && (empty($data["consent_data"]) || empty($data["consented_at"]))) {
            return response()->json(["message" => "You must complete and agree to the consent form before booking."], 422);
        }
        $appt = Appointment::create([
            "user_id" => $request->user()->id,
            "appointment_type_id" => $data["appointment_type_id"],
            "scheduled_at" => $data["scheduled_at"],
            "duration_minutes" => $type->duration_minutes,
            "notes" => $data["notes"] ?? null,
            "status" => "pending",
            "consent_data" => $data["consent_data"] ?? null,
            "consented_at" => $data["consented_at"] ?? null,
            "timezone" => 'Australia/Sydney',
        ]);

        // Generate consent PDF if consent data is present
        if (!empty($data["consent_data"]) && !empty($data["consented_at"]) && $type->consent_template_id) {
            $pdfPath = $this->generateConsentPdf($appt, $type, $request->user());
            if ($pdfPath) {
                $appt->update(['consent_pdf_path' => $pdfPath]);
            }
        }

        $tz = $appt->timezone ?? 'Australia/Sydney';
        $shortcodes = [
            '{username}'             => $request->user()->name,
            '{email}'                => $request->user()->email,
            '{appointment_title}'     => $type->title,
            '{appointment_date}' => Carbon::parse($appt->scheduled_at)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time',
            '{dashboard_url}'        => rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard',
            '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
        ];

        EmailService::send($request->user()->email, 'appointment_booked_pending', $shortcodes);

        return response()->json($appt->load("type"), 201);
    }
    public function cancel(Request $request, string $id): JsonResponse
    {
        $appt = $request->user()->appointments()->with(['type'])->findOrFail($id);
        if (!in_array($appt->status, ["pending", "confirmed"])) {
            return response()->json(["message" => "Cannot cancel this appointment."], 422);
        }
        $reason = $request->reason ?? 'User requested cancellation';
        $appt->update(["status" => "cancelled", "cancellation_reason" => $reason]);

        $tz = $appt->timezone ?? 'Australia/Sydney';
        EmailService::send($request->user()->email, 'appointment_cancelled', [
            '{username}'             => $request->user()->name,
            '{email}'                => $request->user()->email,
            '{appointment_title}'     => $appt->type?->title ?? 'Appointment',
            '{appointment_date}' => Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time',
            '{cancellation_reason}'  => $reason,
            '{dashboard_url}'        => rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard',
            '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
        ]);

        return response()->json(["message" => "Cancelled.", "appointment" => $appt]);
    }

    public function adminCancel(Request $request, string $id): JsonResponse
    {
        $appt = Appointment::with(['user', 'type'])->findOrFail($id);
        if (!in_array($appt->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Cannot cancel this appointment.'], 422);
        }
        $reason = $request->reason ?? 'Cancelled by admin';
        $appt->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);

        if ($appt->user) {
            $tz = $appt->timezone ?? 'Australia/Sydney';
            EmailService::send($appt->user->email, 'appointment_cancelled', [
                '{username}'             => $appt->user->name,
                '{email}'                => $appt->user->email,
                '{appointment_title}'     => $appt->type?->title ?? 'Appointment',
                '{appointment_date}' => Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time',
                '{cancellation_reason}'  => $reason,
                '{dashboard_url}'        => rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard',
                '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
            ]);
        }

        return response()->json(['message' => 'Appointment cancelled.', 'appointment' => $appt]);
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'date'                => ['required', 'date_format:Y-m-d'],
            'appointment_type_id' => ['required', 'exists:appointment_types,id'],
        ]);

        $date   = $request->date;
        $type   = AppointmentType::findOrFail($request->appointment_type_id);
        $dur    = $type->duration_minutes;
        $brk    = $type->break_minutes;
        $block  = $dur + $brk; // minutes occupied per slot

        // Get availability windows for this date.
        // Bypass the X-Company-ID global scope (public frontend never sends that header).
        // Match windows that belong to the type's company OR were created in global mode (company_id NULL).
        $windows = AvailabilitySchedule::withoutCompanyScope()
            ->where(function ($q) use ($type) {
                $q->where('company_id', $type->company_id)
                  ->orWhereNull('company_id');
            })
            ->forDate($date)
            ->orderBy('start_time')
            ->get();
        if ($windows->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $tz = \App\Models\Setting::getValue('timezone') ?? 'Australia/Sydney';

        // Load existing appointments on this date (not cancelled/no_show).
        // scheduled_at is stored UTC; give a ±1 day buffer so timezone shifts never miss a conflict.
        $existing = Appointment::withoutCompanyScope()
            ->where(function ($q) use ($type) {
                $q->where('company_id', $type->company_id)
                  ->orWhereNull('company_id');
            })
            ->with('type')
            ->whereBetween('scheduled_at', [
                Carbon::parse($date . ' 00:00:00', $tz)->utc()->toDateTimeString(),
                Carbon::parse($date . ' 23:59:59', $tz)->utc()->toDateTimeString(),
            ])
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get()
            ->map(function ($appt) use ($tz) {
                $start     = Carbon::parse($appt->scheduled_at)->setTimezone($tz);
                $typeBreak = $appt->type?->break_minutes ?? 0;
                return [
                    'start' => $start,
                    'end'   => $start->copy()->addMinutes($appt->duration_minutes + $typeBreak),
                ];
            });

        $available = [];

        foreach ($windows as $window) {
            $winEnd = Carbon::parse($date . ' ' . $window->end_time, $tz);
            $cursor = Carbon::parse($date . ' ' . $window->start_time, $tz);

            while (true) {
                $sessionEnd   = $cursor->copy()->addMinutes($dur);
                $slotBlockEnd = $cursor->copy()->addMinutes($block);

                // Session must fit within the window
                if ($sessionEnd->gt($winEnd)) {
                    break;
                }

                // Check overlap with any existing appointment (using their duration + break)
                $conflict = false;
                foreach ($existing as $ex) {
                    if ($cursor->lt($ex['end']) && $slotBlockEnd->gt($ex['start'])) {
                        $conflict = true;
                        break;
                    }
                }

                if (!$conflict) {
                    $available[] = $cursor->format('H:i');
                }

                $cursor->addMinutes($block);
            }
        }

        return response()->json(['data' => $available]);
    }
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Appointment::with(["user:id,name,email,avatar", "type:id,title,duration_minutes,price", "company:id,name", "orderItem:id,order_id,subtotal", "orderItem.order:id,order_number,status,payment_method,total,stripe_payment_intent,paypal_payer_email,paid_at"]);
        if ($request->status) { $query->where("status", $request->status); }
        if ($request->id)     { $query->where("id", $request->id); }
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas("user", fn ($u) => $u->where("name", "like", "%{$s}%")->orWhere("email", "like", "%{$s}%"))
                  ->orWhereHas("type", fn ($t) => $t->where("title", "like", "%{$s}%"));
            });
        }
        return response()->json($query->orderBy("scheduled_at", "asc")->paginate(20));
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $appt            = Appointment::findOrFail($id);
        $prevStatus      = $appt->status;
        $prevScheduledAt = $appt->scheduled_at?->toDateTimeString();
        $appt->update($request->validate([
            "status"       => ["sometimes", "in:pending,confirmed,completed,cancelled,no_show"],
            "meeting_link" => ["nullable", "url"],
            "notes"        => ["nullable", "string"],
            "scheduled_at" => ["sometimes", "date"],
        ]));
        $appt->load(["user", "type"]);

        $newStatus    = $appt->status;
        $tz           = $appt->timezone ?? 'Australia/Sydney';
        $datetimeFmt  = Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time';
        $dashboardUrl = rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard';

        // Status-change emails
        if ($prevStatus !== $newStatus && $appt->user) {
            $shortcodes = [
                '{username}'             => $appt->user->name,
                '{email}'                => $appt->user->email,
                '{appointment_title}'     => $appt->type?->title ?? 'Appointment',
                '{appointment_date}' => $datetimeFmt,
                '{cancellation_reason}'  => $appt->cancellation_reason ?? 'Cancelled by admin',
                '{dashboard_url}'        => $dashboardUrl,
                '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
            ];
            if ($newStatus === 'confirmed') {
                EmailService::send($appt->user->email, 'appointment_confirmed', $shortcodes);
            } elseif ($newStatus === 'cancelled') {
                EmailService::send($appt->user->email, 'appointment_cancelled', $shortcodes);
            }
        }

        // Reschedule email — status unchanged but scheduled_at changed
        if ($request->has('scheduled_at') && $prevStatus === $newStatus && $appt->user) {
            if ($prevScheduledAt && $prevScheduledAt !== $appt->scheduled_at?->toDateTimeString()) {
                EmailService::send($appt->user->email, 'appointment_rescheduled', [
                    '{username}'             => $appt->user->name,
                    '{email}'                => $appt->user->email,
                    '{appointment_title}'     => $appt->type?->title ?? 'Appointment',
                    '{appointment_date}' => $datetimeFmt,
                    '{dashboard_url}'        => $dashboardUrl,
                    '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
                ]);
            }
        }

        return response()->json($appt);
    }
    public function confirm(Request $request, string $id): JsonResponse
    {
        $appt = Appointment::with(["user", "type"])->findOrFail($id);

        // Use admin-provided meeting link if given, otherwise try auto-generate
        $meetLink = $request->input('meeting_link') ?: null;
        if (!$meetLink) {
            try {
                $meetLink = (new \App\Services\GoogleMeetService())->createMeetLink();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Google Meet creation failed: ' . $e->getMessage());
            }
        }

        $appt->update(["status" => "confirmed", "meeting_link" => $meetLink]);

        if ($appt->user) {
            $tz           = $appt->timezone ?? 'Australia/Sydney';
            $datetimeFmt  = \Carbon\Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time';
            $dashboardUrl = rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard';
            $adminUrl     = rtrim(config('app.admin_url', config('app.frontend_url', url('/'))), '/');

            $meetLinkHtml = $meetLink
                ? '<p style="margin:24px 0 8px;"><strong>Google Meet Link</strong></p><p style="margin-bottom:24px;"><a href="' . $meetLink . '" style="color:#6366f1;">' . $meetLink . '</a></p>'
                : '';

            // Build Google Calendar "Add to Calendar" link
            $durationMinutes  = $appt->duration_minutes ?? $appt->type?->duration_minutes ?? 60;
            $startUtc         = \Carbon\Carbon::parse($appt->scheduled_at)->utc()->format('Ymd\THis\Z');
            $endUtc           = \Carbon\Carbon::parse($appt->scheduled_at)->addMinutes($durationMinutes)->utc()->format('Ymd\THis\Z');
            $calTitle         = rawurlencode($appt->type?->title ?? 'Appointment');
            $calDetails       = $meetLink ? rawurlencode('Join via Google Meet: ' . $meetLink) : rawurlencode($appt->type?->title ?? 'Appointment');
            $calLocation      = $meetLink ? rawurlencode($meetLink) : '';
            $googleCalUrl     = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$calTitle}&dates={$startUtc}/{$endUtc}&details={$calDetails}&location={$calLocation}";
            $addToCalendarHtml = '<p style="text-align:center;margin:20px 0;"><a href="' . $googleCalUrl . '" target="_blank" style="background:#4285f4;color:#fff;padding:11px 24px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;font-size:14px;">&#128197; Add to Google Calendar</a></p>';

            $shortcodes = [
                '{username}'             => $appt->user->name,
                '{email}'                => $appt->user->email,
                '{appointment_title}'     => $appt->type?->title ?? 'Appointment',
                '{appointment_title}'    => $appt->type?->title ?? 'Appointment',
                '{appointment_date}' => $datetimeFmt,
                '{appointment_date}'     => $datetimeFmt,
                '{meeting_link}'         => $meetLinkHtml,
                '{add_to_calendar}'      => $addToCalendarHtml,
                '{dashboard_url}'        => $dashboardUrl,
                '{site_name}'            => \App\Models\Setting::getValue('site_name') ?? config('app.name'),
            ];

            // Send confirmation email to customer (with meeting link if provided)
            EmailService::send($appt->user->email, 'appointment_confirmed', $shortcodes);

            // Notify admin
            $adminEmail = \App\Models\Setting::getValue('admin_notification_email')
                       ?? \App\Models\User::withoutGlobalScopes()->whereNull('company_id')->value('email');
            if ($adminEmail) {
                EmailService::send($adminEmail, 'appointment_confirmed', array_merge($shortcodes, [
                    '{dashboard_url}' => $adminUrl . '/admin/appointments',
                ]));
            }
        }

        return response()->json($appt->fresh(["user", "type"]));
    }
    public function complete(string $id): JsonResponse
    {
        Appointment::findOrFail($id)->update(["status" => "completed"]);
        return response()->json(Appointment::with(["user", "type"])->find($id));
    }
    public function blockSlot(): JsonResponse
    {
        return response()->json(["message" => "Slot blocked."]);
    }

    /**
     * Schedule an appointment that was already paid (via checkout) but has no scheduled_at yet.
     * Called after the user selects their date/time in the post-payment step.
     * This is where the confirmation email is sent (we now have both payment + schedule).
     */
    public function scheduleOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'     => ['required', 'integer', 'exists:orders,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'booking_data' => ['sometimes', 'nullable', 'array'],
            'consent_data' => ['sometimes', 'nullable', 'array'],
            'consented_at' => ['sometimes', 'nullable', 'string'],
            'notes'        => ['sometimes', 'nullable', 'string'],
        ]);

        $user  = $request->user();
        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'pending'])
            ->with('items')
            ->firstOrFail();

        // If still pending, verify payment with Stripe before proceeding
        if ($order->status === 'pending') {
            $stripeSecret = \App\Models\Setting::getValue('pg_stripe_secret') ?: config('services.stripe.secret', '');
            if ($order->stripe_session_id) {
                if (empty($stripeSecret)) {
                    return response()->json(['message' => 'Stripe is not configured.'], 502);
                }
                try {
                    $stripe  = new \Stripe\StripeClient($stripeSecret);
                    $session = $stripe->checkout->sessions->retrieve($order->stripe_session_id);
                    if ($session->payment_status === 'paid') {
                        $order->update(['status' => 'paid', 'paid_at' => now()]);
                    } else {
                        return response()->json(['message' => 'Payment has not been confirmed yet. Please wait a moment and try again.'], 402);
                    }
                } catch (\Throwable $e) {
                    return response()->json(['message' => 'Could not verify payment: ' . $e->getMessage()], 502);
                }
            } elseif ($order->stripe_payment_intent) {
                if (empty($stripeSecret)) {
                    return response()->json(['message' => 'Stripe is not configured.'], 502);
                }
                try {
                    $stripe = new \Stripe\StripeClient($stripeSecret);
                    $pi     = $stripe->paymentIntents->retrieve($order->stripe_payment_intent);
                    if ($pi->status === 'succeeded') {
                        $order->update(['status' => 'paid', 'paid_at' => now()]);
                    } else {
                        return response()->json(['message' => 'Payment not completed. Please try again.'], 402);
                    }
                } catch (\Throwable $e) {
                    return response()->json(['message' => 'Could not verify payment: ' . $e->getMessage()], 502);
                }
            } else {
                return response()->json(['message' => 'Payment not yet confirmed. Please wait a moment and try again.'], 402);
            }
        }

        $item = $order->items
            ->filter(fn($i) => $i->purchasable_type === AppointmentType::class)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'No appointment type found in this order.'], 404);
        }

        if (Appointment::where('order_item_id', $item->id)->exists()) {
            return response()->json(['message' => 'This appointment has already been scheduled.'], 422);
        }

        $type = AppointmentType::find($item->purchasable_id);
        if (!$type) {
            return response()->json(['message' => 'Appointment type not found.'], 404);
        }

        // Save booking fields to user profile
        if (!empty($data['booking_data'])) {
            $allowed = ['phone', 'date_of_birth', 'time_of_birth', 'place_of_birth'];
            $updates = array_intersect_key($data['booking_data'], array_flip($allowed));
            if (!empty($updates)) {
                $user->fill($updates)->save();
            }
        }

        $appt = Appointment::create([
            'user_id'             => $user->id,
            'appointment_type_id' => $type->id,
            'order_item_id'       => $item->id,
            'scheduled_at'        => $data['scheduled_at'],
            'duration_minutes'    => $type->duration_minutes,
            'notes'               => $data['notes'] ?? null,
            'status'              => 'pending',
            'consent_data'        => $data['consent_data'] ?? null,
            'consented_at'        => $data['consented_at'] ?? null,
            'company_id'          => $type->company_id,
            'timezone'            => 'Australia/Sydney',
        ]);

        // Update order_item meta so admin can see the schedule
        $meta = array_merge((array) ($item->meta ?? []), [
            'scheduled_at' => $data['scheduled_at'],
            'booking_data' => $data['booking_data'] ?? null,
        ]);
        $item->update(['meta' => $meta]);

        // Auto-create Google Meet link
        $meetLink = null;
        try {
            $meetLink = (new \App\Services\GoogleMeetService())->createMeetLink();
            if ($meetLink) {
                $appt->update(['meeting_link' => $meetLink]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google Meet link creation failed: ' . $e->getMessage());
        }

        $tz          = $appt->timezone ?? 'Australia/Sydney';
        $datetimeFmt = Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time';
        $dashboardUrl = rtrim(config('app.frontend_url', url('/')), '/') . '/dashboard';
        $adminUrl     = rtrim(config('app.admin_url', config('app.frontend_url', url('/'))), '/');

        $meetLinkHtml = $meetLink
            ? '<p style="margin:24px 0 8px;"><strong>Google Meet Link</strong></p><p style="margin-bottom:24px;"><a href="' . $meetLink . '" style="color:#6366f1;">' . $meetLink . '</a></p>'
            : '';

        // appointment_confirmed email disabled — admin sends manually with meeting link

        return response()->json(['message' => 'Appointment scheduled.', 'appointment' => $appt->load('type'), 'meeting_link' => $meetLink], 201);
    }

    /**
     * User: download their own consent PDF (ownership enforced).
     */
    public function myConsentPdf(Request $request, string $id): Response|\Illuminate\Http\JsonResponse
    {
        $appt = Appointment::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!$appt->consent_pdf_path || !Storage::disk('local')->exists($appt->consent_pdf_path)) {
            return response()->json(['message' => 'No consent PDF found for this appointment.'], 404);
        }

        $filename = 'consent_appointment_' . $appt->id . '.pdf';
        return response(Storage::disk('local')->get($appt->consent_pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Admin: download consent PDF for an appointment.
     */
    public function downloadConsentPdf(string $id): Response|\Illuminate\Http\JsonResponse
    {
        $appt = Appointment::with(['user', 'type'])->findOrFail($id);

        if (!$appt->consent_pdf_path || !Storage::disk('local')->exists($appt->consent_pdf_path)) {
            return response()->json(['message' => 'No consent PDF found for this appointment.'], 404);
        }

        $filename = 'consent_appointment_' . $appt->id . '.pdf';
        return response(Storage::disk('local')->get($appt->consent_pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate a consent PDF for an appointment and store it locally.
     * Returns the storage path on success, or null on failure.
     */
    private function generateConsentPdf(Appointment $appt, AppointmentType $type, $user): ?string
    {
        try {
            $template = $type->consentTemplate;
            if (!$template) return null;

            $fields    = $template->fields ?? [];
            $answers   = $appt->consent_data ?? [];

            // Strip HTML from disclaimer for clean plain-text rendering
            $disclaimer = $template->disclaimer_html
                ? strip_tags(html_entity_decode($template->disclaimer_html, ENT_QUOTES, 'UTF-8'))
                : null;

            // Build field data with answers merged in
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

            $company = \App\Models\Company::find($appt->company_id);

            $pdf = Pdf::loadView('appointments.consent-pdf', [
                'appointmentId'   => $appt->id,
                'templateName'    => $template->name,
                'userName'        => $user->name,
                'userEmail'       => $user->email,
                'appointmentType' => $type->title,
                'scheduledAt'     => Carbon::parse($appt->scheduled_at)->setTimezone($appt->timezone ?? 'Australia/Sydney')->format('l, F j Y \a\t g:i A'),
                'disclaimer'      => $disclaimer,
                'fields'          => $fieldData,
                'consentedAt'     => $appt->consented_at
                    ? Carbon::parse($appt->consented_at)->format('l, F j Y \a\t g:i A \U\T\C')
                    : Carbon::now()->format('l, F j Y \a\t g:i A \U\T\C'),
                'generatedAt'     => Carbon::now()->format('F j, Y \a\t g:i A'),
                'companyName'     => $company->name ?? config('app.name'),
                'companyLogo'     => $company->logo ?? null,
            ])->setPaper('a4', 'portrait');

            $path = 'consents/appointment_' . $appt->id . '.pdf';
            Storage::disk('local')->put($path, $pdf->output());

            return $path;
        } catch (\Throwable $e) {
            \Log::error('Consent PDF generation failed for appointment ' . $appt->id . ': ' . $e->getMessage());
            return null;
        }
    }
}