<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $studentBody = '<p>Dear {username},</p><p>Thank you for taking the time to share your thoughts on <strong>{course_title}</strong>. We have received your <strong>{rating}</strong> review and it is currently pending approval from our moderation team.</p><p style="font-weight:600;color:#374151;margin-bottom:6px;">Here is a summary of what you shared:</p><blockquote style="border-left:4px solid #6366f1;padding:12px 18px;color:#555;font-style:italic;margin:0 0 20px;background:#f9f9ff;border-radius:0 6px 6px 0;">{comment}</blockquote><p>Once approved, your review will be published and will help guide fellow learners in our community. We sincerely appreciate your contribution.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 28px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px;">Return to Course</a></p><p style="color:#888;font-size:13px;margin-top:28px;">Warm regards,<br><strong>{site_name} Team</strong></p>';

        $adminBody = '<h2 style="color:#1f2937;margin-bottom:4px;">New Review Awaiting Approval</h2><p style="color:#6b7280;margin-top:0;">A learner has submitted a review that requires your attention.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 12px;background:#f3f4f6;font-weight:600;color:#374151;width:120px;">Reviewer</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">{username} &lt;{email}&gt;</td></tr><tr><td style="padding:8px 12px;background:#f3f4f6;font-weight:600;color:#374151;">Course</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">{course_title}</td></tr><tr><td style="padding:8px 12px;background:#f3f4f6;font-weight:600;color:#374151;">Rating</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">{rating}</td></tr></table><p style="font-weight:600;color:#374151;margin-bottom:6px;">Review Comment:</p><blockquote style="border-left:4px solid #6366f1;padding:12px 18px;color:#555;font-style:italic;margin:0 0 20px;background:#f9f9ff;border-radius:0 6px 6px 0;">{comment}</blockquote><p><a href="{admin_reviews_url}" style="background:#6366f1;color:#fff;padding:10px 28px;border-radius:6px;text-decoration:none;display:inline-block;">Review &amp; Approve in Admin Panel</a></p>';

        $studentShortcodes = json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{comment}', '{site_name}']);
        $adminShortcodes   = json_encode(['{username}', '{email}', '{course_title}', '{rating}', '{comment}', '{admin_reviews_url}', '{site_name}']);

        DB::table('email_templates')
            ->where('key', 'review_submitted')
            ->update([
                'subject'              => 'Your review of {course_title} has been received — pending approval',
                'body_html'            => $studentBody,
                'available_shortcodes' => $studentShortcodes,
                'updated_at'           => now(),
            ]);

        DB::table('email_templates')
            ->where('key', 'review_received_admin')
            ->update([
                'subject'              => '[{site_name}] New {rating} review received — {course_title}',
                'body_html'            => $adminBody,
                'available_shortcodes' => $adminShortcodes,
                'updated_at'           => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('key', 'review_submitted')
            ->update([
                'subject'              => 'Thank you for reviewing {course_title}',
                'body_html'            => '<h2>Thanks for your review, {username}!</h2><p>Your {rating}-star review of <strong>{course_title}</strong> has been submitted and is pending approval.</p><p>We appreciate you taking the time to share your experience — it helps our community grow.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Back to Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}']),
                'updated_at'           => now(),
            ]);

        DB::table('email_templates')
            ->where('key', 'review_received_admin')
            ->update([
                'subject'              => 'New {rating}-star review received for {course_title}',
                'body_html'            => '<h2>New Review Received</h2><p><strong>{username}</strong> left a <strong>{rating}-star</strong> review on <strong>{course_title}</strong>.</p><blockquote style="border-left:4px solid #6366f1;padding:8px 16px;color:#555;font-style:italic;">{comment}</blockquote><p><a href="{admin_reviews_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View in Admin Panel</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{rating}', '{comment}', '{admin_reviews_url}', '{site_name}']),
                'updated_at'           => now(),
            ]);
    }
};
