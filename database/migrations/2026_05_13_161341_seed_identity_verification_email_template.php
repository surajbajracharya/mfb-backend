<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function btn(string $url, string $label): string
    {
        return "<p style=\"margin:24px 0;\"><a href=\"{$url}\" style=\"background:#6366f1;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;display:inline-block;font-weight:600;font-size:15px;\">{$label}</a></p>";
    }

    private function alert(string $msg, string $type = 'warning'): string
    {
        $c = $type === 'danger'
            ? ['#fef2f2', '#fecaca', '#b91c1c']
            : ['#fffbeb', '#fde68a', '#b45309'];
        return "<p style=\"font-size:13px;background:{$c[0]};border:1px solid {$c[1]};border-radius:6px;padding:10px 14px;margin:16px 0;color:{$c[2]};\">{$msg}</p>";
    }

    public function up(): void
    {
        $so = '<p style="color:#888;font-size:13px;margin-top:28px;">Warm regards,<br><strong>The {site_name} Team</strong></p>';

        $body = '<p>Dear {username},</p>'
            . '<h2>Identity Verification Required</h2>'
            . '<p>Your <strong>{site_name}</strong> account has been temporarily deactivated for security purposes. To have your account reviewed for reactivation, we need to verify your identity.</p>'
            . '<p>Please click the button below to access our secure verification form, where you will be asked to upload a government-issued photo ID.</p>'
            . $this->btn('{verify_url}', 'Verify My Identity')
            . $this->alert('<strong>Accepted documents:</strong> Passport, Driver\'s Licence, National Identity Card, or any valid government-issued photo ID. Please ensure the image is clear and all details are legible.')
            . '<p style="font-size:13px;color:#6b7280;">This link is valid for <strong>7 days</strong> and can only be used once. Once your identity is verified and approved by our team, you will receive a confirmation email and your account will be reactivated.</p>'
            . '<p style="font-size:13px;color:#6b7280;">If you did not expect this email or have questions, please contact our support team by replying to this message.</p>'
            . $so;

        DB::table('email_templates')->insertOrIgnore([
            'key'                 => 'identity_verification_link',
            'name'                => 'Identity Verification Link',
            'subject'             => 'Action Required: Verify Your Identity — {site_name} Account',
            'body_html'           => $body,
            'available_shortcodes'=> json_encode(['{username}', '{verify_url}', '{site_name}']),
            'is_active'           => 1,
            'company_id'          => null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'identity_verification_link')->delete();
    }
};
