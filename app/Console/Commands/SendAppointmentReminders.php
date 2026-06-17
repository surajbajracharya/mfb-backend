<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Company;
use App\Services\EmailService;
use App\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature   = 'appointments:send-reminders';
    protected $description = 'Send reminder emails for appointments scheduled in the next 24 hours';

    public function handle(): void
    {
        $window_start = now()->addHours(23);
        $window_end   = now()->addHours(25);

        $appointments = Appointment::withoutCompanyScope()
            ->with(['user', 'type', 'company'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('scheduled_at', [$window_start, $window_end])
            ->whereNull('reminder_sent_at')
            ->get();

        $count = 0;

        foreach ($appointments as $appt) {
            if (!$appt->user || !$appt->user->email) {
                continue;
            }

            // Set tenant context so EmailService resolves correct company branding
            if ($appt->company) {
                app(TenantContext::class)->setCompany($appt->company);
            }

            $tz = $appt->timezone ?? config('app.timezone');

            EmailService::send($appt->user->email, 'appointment_reminder', [
                '{username}'          => $appt->user->name,
                '{email}'             => $appt->user->email,
                '{appointment_title}' => $appt->type?->title ?? 'Appointment',
                '{appointment_date}'  => Carbon::parse($appt->scheduled_at)->setTimezone($tz)->format('D, M j Y \a\t g:i A') . ' Australia/Sydney Time',
                '{site_name}'         => config('app.name'),
            ]);

            $appt->update(['reminder_sent_at' => now()]);
            $count++;
        }

        $this->info("Sent {$count} appointment reminder(s).");
    }
}
