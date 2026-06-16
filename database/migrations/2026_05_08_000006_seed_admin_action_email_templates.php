<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->pluck('id')->toArray();

        $templates = [
            [
                'key'                  => 'admin_user_created',
                'name'                 => 'Admin Created Your Account',
                'subject'              => 'Your account on {site_name} is ready',
                'body_html'            => '<h2>Welcome to {site_name}, {username}!</h2><p>An administrator has created an account for you. Here are your login details:</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Email</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{email}</td></tr><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Temporary Password</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{temp_password}</td></tr></table><p style="margin-top:16px;">Please log in and change your password as soon as possible.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Log In Now</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{temp_password}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'account_activated',
                'name'                 => 'Account Activated',
                'subject'              => 'Your {site_name} account has been activated',
                'body_html'            => '<h2>Your Account Is Active!</h2><p>Hi {username}, great news — your account on <strong>{site_name}</strong> has been activated. You can now log in and access all your content.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Go to Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'account_deactivated',
                'name'                 => 'Account Deactivated',
                'subject'              => 'Your {site_name} account has been deactivated',
                'body_html'            => '<h2>Account Deactivated</h2><p>Hi {username}, your account on <strong>{site_name}</strong> has been temporarily deactivated by an administrator.</p><p>If you believe this is a mistake or have any questions, please contact our support team.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'review_unapproved',
                'name'                 => 'Review Unapproved / Hidden',
                'subject'              => 'Your review of {course_title} has been unpublished',
                'body_html'            => '<h2>Review Unpublished</h2><p>Hi {username}, your <strong>{rating}-star</strong> review of <strong>{course_title}</strong> has been unpublished by our team and is no longer publicly visible.</p><p>If you have any questions about this decision, please contact our support team.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'review_deleted',
                'name'                 => 'Review Deleted',
                'subject'              => 'Your review of {course_title} has been removed',
                'body_html'            => '<h2>Review Removed</h2><p>Hi {username}, your review of <strong>{course_title}</strong> has been removed by our team.</p><p>If you have any questions about this decision, please contact our support team.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'course_published',
                'name'                 => 'Course Now Available',
                'subject'              => 'Good news: {course_title} is now available',
                'body_html'            => '<h2>Your Course Is Live!</h2><p>Hi {username}, great news! <strong>{course_title}</strong>, which you are enrolled in, is now published and available for you to continue learning.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Continue Learning</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'course_archived',
                'name'                 => 'Course Temporarily Unavailable',
                'subject'              => '{course_title} is temporarily unavailable',
                'body_html'            => '<h2>Course Temporarily Unavailable</h2><p>Hi {username}, we wanted to let you know that <strong>{course_title}</strong>, which you are enrolled in, has been temporarily archived and is currently unavailable.</p><p>Your progress has been saved. We will notify you when the course is available again.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View My Courses</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'event_published',
                'name'                 => 'New Event Published',
                'subject'              => 'New event: {event_title}',
                'body_html'            => '<h2>New Event: {event_title}</h2><p>Hi {username}, a new event is now available on <strong>{site_name}</strong>.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Event</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{event_title}</td></tr><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{event_date}</td></tr></table><p style="margin-top:16px;"><a href="{event_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Event</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{event_title}', '{event_date}', '{event_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'resource_published',
                'name'                 => 'New Resource Available',
                'subject'              => 'New resource: {resource_title}',
                'body_html'            => '<h2>New Resource: {resource_title}</h2><p>Hi {username}, a new resource is now available on <strong>{site_name}</strong> as part of your membership.</p><p><a href="{resource_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Resource</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{resource_title}', '{resource_url}', '{site_name}']),
                'is_active'            => true,
            ],
        ];

        foreach ($companies as $companyId) {
            foreach ($templates as $tpl) {
                $exists = DB::table('email_templates')
                    ->where('key', $tpl['key'])
                    ->where('company_id', $companyId)
                    ->exists();

                if (!$exists) {
                    DB::table('email_templates')->insert(array_merge($tpl, [
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'admin_user_created', 'account_activated', 'account_deactivated',
            'review_unapproved', 'review_deleted',
            'course_published', 'course_archived',
            'event_published', 'resource_published',
        ];
        DB::table('email_templates')->whereIn('key', $keys)->delete();
    }
};
