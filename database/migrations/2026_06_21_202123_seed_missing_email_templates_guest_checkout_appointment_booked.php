<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'key'                  => 'guest_checkout_welcome',
                'name'                 => 'Guest Checkout Welcome',
                'subject'              => 'Welcome to {site_name} — Your account details',
                'body_html'            => '<h2>Welcome to {site_name}, {username}!</h2><p>Your account has been created automatically after your purchase. Here are your login details:</p><table style="border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Login URL</td><td style="padding:8px 0;"><a href="{login_url}" style="color:#4f46e5;">{login_url}</a></td></tr><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Email</td><td style="padding:8px 0;">{email}</td></tr><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Password</td><td style="padding:8px 0;font-family:monospace;font-weight:bold;">{temp_password}</td></tr></table><p>We recommend changing your password after logging in.</p><p style="text-align:center;margin:32px 0;"><a href="{login_url}" style="background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Log In Now</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{temp_password}', '{login_url}', '{site_name}']),
            ],
            [
                'key'                  => 'appointment_booked_pending',
                'name'                 => 'Appointment Booked (Pending)',
                'subject'              => 'Appointment request received — {site_name}',
                'body_html'            => '<h2>Appointment Request Received, {username}!</h2><p>Thank you for booking an appointment for <strong>{appointment_title}</strong>. Your request is currently pending confirmation.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Appointment</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_title}</td></tr><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date and Time</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_date}</td></tr></table><p>We will confirm your appointment shortly. You can view your bookings from your dashboard.</p><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">View My Appointments</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{dashboard_url}', '{site_name}']),
            ],
        ];

        foreach ($templates as $t) {
            $exists = DB::table('email_templates')
                ->whereNull('company_id')
                ->where('key', $t['key'])
                ->exists();

            if (!$exists) {
                DB::table('email_templates')->insert(array_merge($t, [
                    'is_active'  => 1,
                    'company_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereNull('company_id')
            ->whereIn('key', ['guest_checkout_welcome', 'appointment_booked_pending'])
            ->delete();
    }
};
