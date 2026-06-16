<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // e.g. 'welcome', 'order_confirmed'
            $table->string('name');                   // Human-readable label
            $table->string('subject');
            $table->longText('body_html');            // HTML body (may contain {shortcodes})
            $table->json('available_shortcodes');     // array of shortcode keys this template supports
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $templates = [
            [
                'key'                  => 'welcome',
                'name'                 => 'Welcome Email',
                'subject'              => 'Welcome to {site_name}, {username}!',
                'body_html'            => '<h2>Welcome, {username}!</h2><p>We\'re thrilled to have you join <strong>{site_name}</strong>. Your journey to wellness and mindfulness starts now.</p><p>Explore our courses and begin your transformation today.</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Go to Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{site_name}', '{dashboard_url}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'email_verification',
                'name'                 => 'Email Verification',
                'subject'              => 'Verify your email address',
                'body_html'            => '<h2>Hi {username},</h2><p>Please verify your email address by clicking the button below.</p><p><a href="{verification_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Verify Email</a></p><p>This link is valid for one-time use only. If you did not create an account, you can safely ignore this email.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{verification_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'password_reset',
                'name'                 => 'Password Reset',
                'subject'              => 'Reset your password',
                'body_html'            => '<h2>Hi {username},</h2><p>You requested a password reset. Click the button below to set a new password.</p><p><a href="{reset_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Reset Password</a></p><p>If you did not request this, you can safely ignore this email.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{reset_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'order_confirmed',
                'name'                 => 'Order Confirmed',
                'subject'              => 'Your order #{order_id} is confirmed',
                'body_html'            => '<h2>Thank you for your purchase, {username}!</h2><p>Your order <strong>#{order_id}</strong> has been confirmed.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Order Total</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{order_total}</td></tr><tr><td style="padding:8px;"><strong>Date</strong></td><td style="padding:8px;">{order_date}</td></tr></table><p style="margin-top:16px;"><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">View Dashboard</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{order_id}', '{order_total}', '{order_date}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'enrollment_confirmed',
                'name'                 => 'Enrollment Confirmed',
                'subject'              => 'You are enrolled in {course_title}',
                'body_html'            => '<h2>You\'re enrolled, {username}!</h2><p>You now have access to <strong>{course_title}</strong>.</p><p>Start learning at your own pace — we\'re excited to have you in the course.</p><p><a href="{course_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Start Learning</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{course_url}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'certificate_issued',
                'name'                 => 'Certificate Issued',
                'subject'              => 'Your certificate for {course_title} is ready!',
                'body_html'            => '<h2>Congratulations, {username}! 🎉</h2><p>You have successfully completed <strong>{course_title}</strong> and your certificate is ready for download.</p><p><strong>Certificate No:</strong> {certificate_number}</p><p><a href="{certificate_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Download Certificate</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{course_title}', '{certificate_number}', '{certificate_url}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'appointment_confirmed',
                'name'                 => 'Appointment Confirmed',
                'subject'              => 'Your appointment is confirmed',
                'body_html'            => '<h2>Appointment Confirmed, {username}!</h2><p>Your appointment for <strong>{appointment_type}</strong> has been confirmed.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Date &amp; Time</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{appointment_datetime}</td></tr></table><p style="margin-top:16px;">We look forward to seeing you!</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'appointment_reminder',
                'name'                 => 'Appointment Reminder',
                'subject'              => 'Reminder: Your appointment is tomorrow',
                'body_html'            => '<h2>Reminder, {username}!</h2><p>This is a friendly reminder that your <strong>{appointment_type}</strong> appointment is scheduled for <strong>{appointment_datetime}</strong>.</p><p>Please make sure you\'re prepared and on time.</p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'event_ticket',
                'name'                 => 'Event Ticket',
                'subject'              => 'Your ticket for {event_title}',
                'body_html'            => '<h2>You\'re going to {event_title}!</h2><p>Hi {username}, your ticket has been confirmed.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Event</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{event_title}</td></tr><tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{event_date}</td></tr><tr><td style="padding:8px;"><strong>Ticket No.</strong></td><td style="padding:8px;">{ticket_number}</td></tr></table>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{event_title}', '{event_date}', '{ticket_number}', '{site_name}']),
                'is_active'            => true,
            ],
            [
                'key'                  => 'subscription_started',
                'name'                 => 'Subscription Started',
                'subject'              => 'Your {plan_name} subscription is active',
                'body_html'            => '<h2>Welcome to {plan_name}, {username}!</h2><p>Your subscription is now active. You have unlimited access to all included courses and content.</p><p><strong>Next billing date:</strong> {next_billing_date}</p><p><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;">Explore Courses</a></p>',
                'available_shortcodes' => json_encode(['{username}', '{email}', '{plan_name}', '{next_billing_date}', '{dashboard_url}', '{site_name}']),
                'is_active'            => true,
            ],
        ];

        DB::table('email_templates')->insert(array_map(function ($t) {
            return array_merge($t, ['created_at' => now(), 'updated_at' => now()]);
        }, $templates));
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
