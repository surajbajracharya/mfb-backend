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
                'key'                  => 'appointment_cancelled',
                'name'                 => 'Appointment Cancelled',
                'subject'              => 'Your appointment has been cancelled',
                'body_html'            => '<h2>Appointment Cancelled</h2><p>Hi {username}, your <strong>{appointment_type}</strong> appointment scheduled for <strong>{appointment_datetime}</strong> has been cancelled.</p><p><strong>Reason:</strong> {cancellation_reason}</p><p>If you have any questions or would like to rebook, please visit your dashboard.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Book a New Appointment</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{cancellation_reason}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'appointment_rescheduled',
                'name'                 => 'Appointment Rescheduled',
                'subject'              => 'Your appointment has been rescheduled',
                'body_html'            => '<h2>Appointment Rescheduled</h2><p>Hi {username}, your <strong>{appointment_type}</strong> appointment has been rescheduled to a new time.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>New Date &amp; Time</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{appointment_datetime}</td></tr></table><p style="margin-top:16px;">If this new time does not work for you, please contact us to reschedule.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View My Appointments</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'appointment_booked_pending',
                'name'                 => 'Appointment Booked – Pending Confirmation',
                'subject'              => 'Your appointment request has been received',
                'body_html'            => '<h2>Booking Request Received</h2><p>Hi {username}, we have received your booking request for <strong>{appointment_type}</strong>.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Requested Date &amp; Time</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{appointment_datetime}</td></tr></table><p style="margin-top:16px;">Your appointment is currently <strong>pending confirmation</strong>. We will send you another email once it is confirmed.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View My Appointments</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'order_cancelled',
                'name'                 => 'Order Cancelled',
                'subject'              => 'Your order #{order_id} has been cancelled',
                'body_html'            => '<h2>Order Cancelled</h2><p>Hi {username}, your order <strong>#{order_id}</strong> has been cancelled.</p><p>If you believe this is an error or have any questions, please contact our support team.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{order_id}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'order_refunded',
                'name'                 => 'Order Refunded',
                'subject'              => 'Refund processed for order #{order_id}',
                'body_html'            => '<h2>Refund Processed</h2><p>Hi {username}, a refund has been processed for your order <strong>#{order_id}</strong>.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Refund Amount</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{order_total}</td></tr></table><p style="margin-top:16px;">Please allow 5–10 business days for the funds to appear in your account depending on your bank.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{order_id}', '{order_total}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'password_changed',
                'name'                 => 'Password Changed',
                'subject'              => 'Your password has been changed',
                'body_html'            => '<h2>Password Changed</h2><p>Hi {username}, this is a confirmation that the password for your account (<strong>{email}</strong>) was successfully changed.</p><p>If you did not make this change, please contact us immediately to secure your account.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'subscription_cancelled',
                'name'                 => 'Subscription Cancelled',
                'subject'              => 'Your {plan_name} subscription has been cancelled',
                'body_html'            => '<h2>Subscription Cancelled</h2><p>Hi {username}, your <strong>{plan_name}</strong> subscription has been cancelled.</p><p>You may continue to access your content until the end of your current billing period.</p><p>We hope to see you again soon. If you change your mind, you can resubscribe at any time.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{plan_name}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'subscription_expired',
                'name'                 => 'Subscription Expired',
                'subject'              => 'Your {plan_name} subscription has expired',
                'body_html'            => '<h2>Subscription Expired</h2><p>Hi {username}, your <strong>{plan_name}</strong> subscription has expired.</p><p>To continue accessing all included courses and benefits, please renew your subscription.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Renew Subscription</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{plan_name}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'review_approved',
                'name'                 => 'Review Approved',
                'subject'              => 'Your review of {course_title} has been approved',
                'body_html'            => '<h2>Your Review Is Live!</h2><p>Hi {username}, great news! Your <strong>{rating}-star</strong> review of <strong>{course_title}</strong> has been approved and is now publicly visible on the course page.</p><p>Thank you for sharing your experience with our community.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'review_replied',
                'name'                 => 'Admin Replied to Your Review',
                'subject'              => 'The team replied to your review of {course_title}',
                'body_html'            => '<h2>New Reply to Your Review</h2><p>Hi {username}, the team at <strong>{site_name}</strong> has replied to your review of <strong>{course_title}</strong>.</p><blockquote style="border-left:4px solid #6366f1;padding:8px 16px;color:#555;margin:16px 0;font-style:italic;">{admin_reply}</blockquote><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Course</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{admin_reply}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'event_cancelled',
                'name'                 => 'Event Cancelled (to Ticket Holders)',
                'subject'              => 'Important: {event_title} has been cancelled',
                'body_html'            => '<h2>Event Cancelled</h2><p>Hi {username}, we regret to inform you that <strong>{event_title}</strong> scheduled for <strong>{event_date}</strong> has been cancelled.</p><p>Your ticket <strong>#{ticket_number}</strong> will be refunded. Please allow 5–10 business days for the funds to appear in your account.</p><p>We sincerely apologise for any inconvenience this may cause.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{event_title}', '{event_date}', '{ticket_number}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'event_ticket_cancelled',
                'name'                 => 'Event Ticket Cancelled',
                'subject'              => 'Your ticket for {event_title} has been cancelled',
                'body_html'            => '<h2>Ticket Cancelled</h2><p>Hi {username}, your ticket <strong>#{ticket_number}</strong> for <strong>{event_title}</strong> has been cancelled.</p><p>If you have any questions about your cancellation or refund, please contact our support team.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{event_title}', '{ticket_number}', '{site_name}']),
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
            'appointment_cancelled', 'appointment_rescheduled', 'appointment_booked_pending',
            'order_cancelled', 'order_refunded', 'password_changed',
            'subscription_cancelled', 'subscription_expired',
            'review_approved', 'review_replied',
            'event_cancelled', 'event_ticket_cancelled',
        ];
        DB::table('email_templates')->whereIn('key', $keys)->delete();
    }
};
