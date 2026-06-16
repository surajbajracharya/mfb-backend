<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $so = '<br><p style="color:#888;font-size:13px;margin-top:28px;">Warm regards,<br><strong>The {site_name} Team</strong></p>';

    private function btn(string $url, string $label): string
    {
        return '<p><a href="' . $url . '" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;display:inline-block;">' . $label . '</a></p>';
    }

    private function tbl(array $rows): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        foreach ($rows as $i => [$label, $value]) {
            $border = ($i < count($rows) - 1) ? 'border-bottom:1px solid #e5e7eb;' : '';
            $html  .= '<tr><td style="padding:8px 12px;background:#f3f4f6;font-weight:600;color:#374151;width:160px;">' . $label . '</td>'
                    . '<td style="padding:8px 12px;' . $border . '">' . $value . '</td></tr>';
        }
        return $html . '</table>';
    }

    public function up(): void
    {
        // ── Step 1: Add <br> after every Dear greeting across ALL companies ─────
        DB::statement("UPDATE email_templates SET body_html = REPLACE(body_html, '<p>Dear {username},</p>', '<p>Dear {username},</p><br>'), updated_at = NOW() WHERE body_html LIKE '%<p>Dear {username},</p>%' AND body_html NOT LIKE '%<p>Dear {username},</p><br>%'");
        DB::statement("UPDATE email_templates SET body_html = REPLACE(body_html, '<p>Dear Admin,</p>', '<p>Dear Admin,</p><br>'), updated_at = NOW() WHERE body_html LIKE '%<p>Dear Admin,</p>%' AND body_html NOT LIKE '%<p>Dear Admin,</p><br>%'");

        // ── Step 2: Fix missing 'The' in review_submitted sign-off ───────────────
        $templates = DB::table('email_templates')->where('key', 'review_submitted')->get(['id', 'body_html']);
        foreach ($templates as $t) {
            $fixed = str_replace(
                'Warm regards,<br><strong>{site_name} Team</strong>',
                'Warm regards,<br><strong>The {site_name} Team</strong>',
                $t->body_html
            );
            if ($fixed !== $t->body_html) {
                DB::table('email_templates')->where('id', $t->id)->update(['body_html' => $fixed, 'updated_at' => now()]);
            }
        }

        // ── Step 3: Per-template language fixes ──────────────────────────────────

        $so = $this->so;

        // account_activated — "always here to help" → professional
        DB::table('email_templates')->where('key', 'account_activated')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Your Account Is Now Active</h2>'
                . '<p>Your account on <strong>{site_name}</strong> has been successfully activated. You now have full access to all courses, content, and features included in your plan.</p>'
                . '<p>Should you have any questions, our support team is available to assist you.</p>'
                . $this->btn('{dashboard_url}', 'Go to Dashboard') . $so,
            'updated_at' => now(),
        ]);

        // appointment_reminder — "friendly reminder" is too casual; ends without CTA
        DB::table('email_templates')->where('key', 'appointment_reminder')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Upcoming Appointment Reminder</h2>'
                . '<p>Please find below a reminder of your upcoming appointment with <strong>{site_name}</strong>.</p>'
                . $this->tbl([['Type', '{appointment_type}'], ['Date &amp; Time', '{appointment_datetime}']])
                . '<p>If you need to reschedule or have any questions, please contact us at your earliest convenience.</p>'
                . $this->btn('{dashboard_url}', 'View My Appointments') . $so,
            'updated_at' => now(),
        ]);

        // course_archived — "We wanted to let you know" is weak
        DB::table('email_templates')->where('key', 'course_archived')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Course Temporarily Unavailable</h2>'
                . '<p>We wish to inform you that <strong>{course_title}</strong>, in which you are currently enrolled, has been temporarily archived and is unavailable for access.</p>'
                . '<p>Your learning progress has been preserved and will remain fully intact when the course is restored. We will notify you by email as soon as it becomes available again.</p>'
                . $this->btn('{dashboard_url}', 'View My Courses') . $so,
            'updated_at' => now(),
        ]);

        // course_published — "great news" is too informal
        DB::table('email_templates')->where('key', 'course_published')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Your Course Is Now Available</h2>'
                . '<p>We are pleased to inform you that <strong>{course_title}</strong>, in which you are currently enrolled, is now published and available for access.</p>'
                . '<p>You may log in at any time to continue your learning at your own pace.</p>'
                . $this->btn('{course_url}', 'Continue Learning') . $so,
            'updated_at' => now(),
        ]);

        // order_cancelled — "happy to assist" → "a member of our team will respond promptly"
        DB::table('email_templates')->where('key', 'order_cancelled')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Order Cancelled</h2>'
                . '<p>Your order <strong>#{order_id}</strong> has been cancelled. If a payment was made, a refund will be processed and should appear in your account within 5–10 business days.</p>'
                . '<p>If you believe this cancellation was made in error or require further assistance, please contact our support team and a member of our team will respond promptly.</p>'
                . $this->btn('{dashboard_url}', 'View Dashboard') . $so,
            'updated_at' => now(),
        ]);

        // review_unapproved — "happy to assist" → "we will look into it promptly"
        DB::table('email_templates')->where('key', 'review_unapproved')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Review Temporarily Unpublished</h2>'
                . '<p>Your <strong>{rating}</strong> review of <strong>{course_title}</strong> has been temporarily removed from public view by our moderation team.</p>'
                . '<p>If you have any questions or would like to discuss this matter further, please contact our support team and we will look into it promptly.</p>'
                . $this->btn('{course_url}', 'View Course') . $so,
            'updated_at' => now(),
        ]);

        // subscription_cancelled — "sorry to see you go" / "we would love to hear from you" → formal
        DB::table('email_templates')->where('key', 'subscription_cancelled')->update([
            'body_html'  => '<p>Dear {username},</p><br>'
                . '<h2>Subscription Cancelled</h2>'
                . '<p>Your <strong>{plan_name}</strong> subscription has been successfully cancelled.</p>'
                . '<p>You will continue to have access to your subscription benefits until the end of your current billing period, after which your access will revert to the standard tier.</p>'
                . '<p>We regret to see your subscription come to an end. Should you decide to resubscribe or wish to share any feedback, please do not hesitate to contact us.</p>'
                . $this->btn('{dashboard_url}', 'View Dashboard') . $so,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally left empty — rolling back email language is not safe
    }
};
