<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')->value('id');

        $templates = [
            [
                'key'                  => 'review_submitted',
                'name'                 => 'Review Submitted (to Student)',
                'subject'              => 'Thank you for reviewing {course_title}',
                'body_html'            => '<h2>Thanks for your review, {username}!</h2><p>Your {rating}-star review of <strong>{course_title}</strong> has been submitted and is pending approval.</p><p>We appreciate you taking the time to share your experience — it helps our community grow.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Back to Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}']),
                'is_active'            => true,
                'company_id'           => $companyId,
            ],
            [
                'key'                  => 'review_received_admin',
                'name'                 => 'New Review Received (to Admin)',
                'subject'              => 'New {rating}-star review received for {course_title}',
                'body_html'            => '<h2>New Review Received</h2><p><strong>{username}</strong> left a <strong>{rating}-star</strong> review on <strong>{course_title}</strong>.</p><blockquote style="border-left:4px solid #6366f1;padding:8px 16px;color:#555;font-style:italic;">{comment}</blockquote><p><a href="{admin_reviews_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View in Admin Panel</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{rating}', '{comment}', '{admin_reviews_url}', '{site_name}']),
                'is_active'            => true,
                'company_id'           => $companyId,
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('email_templates')
                ->where('key', $template['key'])
                ->where('company_id', $companyId)
                ->exists();
            if (!$exists) {
                DB::table('email_templates')->insert(array_merge($template, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', ['review_submitted', 'review_received_admin'])->delete();
    }
};
