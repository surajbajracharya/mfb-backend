<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $body = '<h2>Welcome to {site_name}, {username}!</h2>'
            . '<p>Your account has been created automatically after your purchase. Here are your login details:</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Login URL</td>'
            . '<td style="padding:8px 0;font-weight:bold;"><a href="{login_url}">{login_url}</a></td></tr>'
            . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Email</td>'
            . '<td style="padding:8px 0;font-weight:bold;">{email}</td></tr>'
            . '<tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Password</td>'
            . '<td style="padding:8px 0;font-weight:bold;font-family:monospace;">{temp_password}</td></tr>'
            . '</table>'
            . '<p>We recommend changing your password after logging in.</p>'
            . '<p style="text-align:center;margin:32px 0;">'
            . '<a href="{login_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Log In to Your Account</a>'
            . '</p>'
            . '<p>Warm regards,<br>The {site_name} Team</p>';

        DB::table('email_templates')->updateOrInsert(
            ['key' => 'guest_checkout_welcome'],
            [
                'name'                 => 'Guest Checkout — Account Created',
                'subject'              => 'Your account has been created — {site_name}',
                'body_html'            => $body,
                'available_shortcodes' => json_encode(['{username}', '{email}', '{login_url}', '{temp_password}', '{site_name}']),
                'is_active'            => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'guest_checkout_welcome')->delete();
    }
};
