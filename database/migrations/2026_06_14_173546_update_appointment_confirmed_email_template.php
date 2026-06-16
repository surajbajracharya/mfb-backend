<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newBody = '<h2>Appointment Confirmed, {username}!</h2>'
            . '<p>Your appointment for <strong>{appointment_title}</strong> is confirmed.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date and Time</td>'
            . '<td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_date}</td></tr>'
            . '</table>'
            . '<p style="margin:24px 0 8px;"><strong>Google Meet Link</strong></p>'
            . '<p style="margin-bottom:24px;">{meeting_link}</p>'
            . '<p>If you need to reschedule or cancel, please contact us in advance.</p>';

        DB::table('email_templates')
            ->where('key', 'appointment_confirmed')
            ->update([
                'body_html'            => $newBody,
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{meeting_link}', '{site_name}']),
            ]);
    }

    public function down(): void
    {
        $oldBody = '<h2>Appointment Confirmed, {username}!</h2>'
            . '<p>Your appointment for <strong>{appointment_title}</strong> is confirmed.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date and Time</td>'
            . '<td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_date}</td></tr>'
            . '</table>'
            . '<p>If you need to reschedule or cancel, please contact us in advance.</p>';

        DB::table('email_templates')
            ->where('key', 'appointment_confirmed')
            ->update([
                'body_html'            => $oldBody,
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{site_name}']),
            ]);
    }
};
