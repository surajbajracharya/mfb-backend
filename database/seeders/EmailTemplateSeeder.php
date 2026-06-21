<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key'     => 'welcome',
                'name'    => 'Welcome Email',
                'subject' => 'Welcome to {site_name}!',
                'body_html' => '<h2>Welcome, {username}!</h2><p>We\'re thrilled to have you join {site_name}. Your account has been created successfully.</p><p>Start exploring our courses, events, and resources to begin your wellness journey.</p><p>If you have any questions, feel free to reply to this email.</p><p>Warm regards,<br>The {site_name} Team</p>',
                'available_shortcodes' => ['{username}', '{email}', '{site_name}', '{dashboard_url}'],
            ],
            [
                'key'     => 'email_verification',
                'name'    => 'Email Verification',
                'subject' => 'Verify your email address — {site_name}',
                'body_html' => '<h2>Hello {username},</h2><p>Thank you for registering. Please verify your email address by clicking the button below.</p><p style="text-align:center;margin:32px 0;"><a href="{verification_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Verify Email Address</a></p><p>If you did not create an account, you can safely ignore this email.</p><p>This link expires in 60 minutes.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{verification_url}', '{site_name}'],
            ],
            [
                'key'     => 'password_reset',
                'name'    => 'Password Reset',
                'subject' => 'Reset your password — {site_name}',
                'body_html' => '<h2>Hello {username},</h2><p>You requested a password reset for your {site_name} account. Click the button below to set a new password.</p><p style="text-align:center;margin:32px 0;"><a href="{reset_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Reset My Password</a></p><p>This link expires in 60 minutes. If you did not request a password reset, no action is needed.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{reset_url}', '{site_name}'],
            ],
            [
                'key'     => 'password_changed',
                'name'    => 'Password Changed',
                'subject' => 'Your password was changed — {site_name}',
                'body_html' => '<h2>Hello {username},</h2><p>This is a confirmation that the password for your {site_name} account was changed successfully.</p><p>If you did not make this change, please contact our support team immediately.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{site_name}'],
            ],
            [
                'key'     => 'order_confirmed',
                'name'    => 'Order Confirmation',
                'subject' => 'Your order is confirmed — {site_name}',
                'body_html' => '<h2>Thank you for your purchase, {username}!</h2><p>Your order <strong>#{order_id}</strong> has been confirmed.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Item</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{item_name}</td></tr><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Order Total</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{order_total}</td></tr><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date</td><td style="padding:8px 0;text-align:right;">{order_date}</td></tr></table><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Go to My Dashboard</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{order_id}', '{item_name}', '{order_total}', '{order_date}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'enrollment_confirmed',
                'name'    => 'Enrollment Confirmed',
                'subject' => 'You are enrolled in {course_title}',
                'body_html' => '<h2>You\'re in, {username}!</h2><p>You have successfully enrolled in <strong>{course_title}</strong>. Start learning at your own pace.</p><p style="text-align:center;margin:32px 0;"><a href="{course_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Start Learning</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{course_title}', '{course_url}', '{site_name}'],
            ],
            [
                'key'     => 'certificate_issued',
                'name'    => 'Certificate Issued',
                'subject' => 'Your certificate is ready — {course_title}',
                'body_html' => '<h2>Congratulations, {username}!</h2><p>You have successfully completed <strong>{course_title}</strong> and your certificate has been issued.</p><p>Certificate Number: <strong>{certificate_number}</strong></p><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Download Certificate</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{course_title}', '{certificate_number}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'appointment_confirmed',
                'name'    => 'Appointment Confirmed',
                'subject' => 'Your appointment is confirmed — {site_name}',
                'body_html' => '<h2>Appointment Confirmed, {username}!</h2><p>Your appointment for <strong>{appointment_title}</strong> is confirmed.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date and Time</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_date}</td></tr></table>{add_to_calendar}{meeting_link}<p>If you need to reschedule or cancel, please contact us in advance.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{meeting_link}', '{add_to_calendar}', '{site_name}'],
            ],
            [
                'key'     => 'appointment_reminder',
                'name'    => 'Appointment Reminder',
                'subject' => 'Reminder: Your appointment tomorrow — {site_name}',
                'body_html' => '<h2>Reminder, {username}!</h2><p>This is a friendly reminder about your upcoming appointment for <strong>{appointment_title}</strong> tomorrow at <strong>{appointment_date}</strong>.</p><p>Please ensure you are prepared and available at the scheduled time.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{site_name}'],
            ],
            [
                'key'     => 'event_ticket',
                'name'    => 'Event Ticket / Registration',
                'subject' => 'Your ticket for {event_title}',
                'body_html' => '<h2>You\'re registered, {username}!</h2><p>Your registration for <strong>{event_title}</strong> is confirmed.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Event Date</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{event_date}</td></tr><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Ticket Number</td><td style="padding:8px 0;font-family:monospace;text-align:right;">{ticket_number}</td></tr></table><p>We look forward to seeing you there. A join link will be sent closer to the event if it is online.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{event_title}', '{event_date}', '{ticket_number}', '{site_name}'],
            ],
            [
                'key'     => 'subscription_started',
                'name'    => 'Subscription Started',
                'subject' => 'Your {plan_name} plan is now active — {site_name}',
                'body_html' => '<h2>Welcome to {plan_name}, {username}!</h2><p>Your subscription to <strong>{plan_name}</strong> is now active. You now have full access to all included content.</p><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Explore Your Benefits</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{plan_name}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'order_refunded',
                'name'    => 'Order Refunded',
                'subject' => 'Your refund has been processed — {site_name}',
                'body_html' => '<h2>Refund Processed, {username}!</h2><p>Your refund for order <strong>#{order_id}</strong> totalling <strong>{order_total}</strong> has been processed. Please allow 5-10 business days for the funds to appear in your account.</p><p>If you have any questions, please contact us.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{order_id}', '{order_total}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'order_cancelled',
                'name'    => 'Order Cancelled',
                'subject' => 'Your order has been cancelled — {site_name}',
                'body_html' => '<h2>Order Cancelled, {username}</h2><p>Your order has been cancelled. If you believe this is an error or have any questions, please reach out to our support team.</p><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Go to Dashboard</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{order_id}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'admin_user_created',
                'name'    => 'Admin Account Created',
                'subject' => 'Your admin account on {site_name}',
                'body_html' => '<p>Hello <strong>{username}</strong>,</p><p>Your admin account has been created on <strong>{site_name}</strong>. Here are your login credentials:</p><table style="border-collapse:collapse;margin:16px 0;"><tr><td style="padding:6px 12px 6px 0;font-weight:600;color:#374151;">Email</td><td style="padding:6px 0;color:#111827;">{email}</td></tr><tr><td style="padding:6px 12px 6px 0;font-weight:600;color:#374151;">Password</td><td style="padding:6px 0;color:#111827;font-family:monospace;">{temp_password}</td></tr></table><p style="margin:24px 0;"><a href="{login_url}" style="display:inline-block;background:#4f46e5;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">Log In to Admin Panel</a></p><p style="color:#6b7280;font-size:14px;">For security, please change your password after your first login.</p><p style="color:#6b7280;font-size:14px;">If you did not expect this email, please contact your system administrator.</p>',
                'available_shortcodes' => ['{username}', '{email}', '{temp_password}', '{login_url}', '{site_name}'],
            ],
            [
                'key'     => 'guest_checkout_welcome',
                'name'    => 'Guest Checkout Welcome',
                'subject' => 'Welcome to {site_name} — Your account details',
                'body_html' => '<h2>Welcome to {site_name}, {username}!</h2><p>Your account has been created automatically after your purchase. Here are your login details:</p><table style="border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Login URL</td><td style="padding:8px 0;"><a href="{login_url}" style="color:#4f46e5;">{login_url}</a></td></tr><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Email</td><td style="padding:8px 0;">{email}</td></tr><tr><td style="padding:8px 12px 8px 0;font-weight:600;color:#374151;">Password</td><td style="padding:8px 0;font-family:monospace;font-weight:bold;">{temp_password}</td></tr></table><p>We recommend changing your password after logging in.</p><p style="text-align:center;margin:32px 0;"><a href="{login_url}" style="background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Log In Now</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{temp_password}', '{login_url}', '{site_name}'],
            ],
            [
                'key'     => 'appointment_booked_pending',
                'name'    => 'Appointment Booked (Pending)',
                'subject' => 'Appointment request received — {site_name}',
                'body_html' => '<h2>Appointment Request Received, {username}!</h2><p>Thank you for booking an appointment for <strong>{appointment_title}</strong>. Your request is currently pending confirmation.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;"><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Appointment</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_title}</td></tr><tr><td style="padding:8px 0;color:#6b7280;font-size:14px;">Date and Time</td><td style="padding:8px 0;font-weight:bold;text-align:right;">{appointment_date}</td></tr></table><p>We will confirm your appointment shortly. You can view your bookings from your dashboard.</p><p style="text-align:center;margin:32px 0;"><a href="{dashboard_url}" style="background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">View My Appointments</a></p>',
                'available_shortcodes' => ['{username}', '{email}', '{appointment_title}', '{appointment_date}', '{dashboard_url}', '{site_name}'],
            ],
            [
                'key'     => 'admin_new_order',
                'name'    => 'Admin: New Order Notification',
                'subject' => 'New order received — #{order_id} ({order_total})',
                'body_html' => '<h2>New Order Received</h2><p>A new order has been placed on <strong>{site_name}</strong>.</p><table style="width:100%;border-collapse:collapse;margin:16px 0;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;"><tr style="background:#f9fafb;"><td style="padding:10px 16px;color:#6b7280;font-size:14px;font-weight:600;">Order ID</td><td style="padding:10px 16px;font-weight:bold;">#{order_id}</td></tr><tr><td style="padding:10px 16px;color:#6b7280;font-size:14px;font-weight:600;">Customer</td><td style="padding:10px 16px;">{customer_name} ({customer_email})</td></tr><tr style="background:#f9fafb;"><td style="padding:10px 16px;color:#6b7280;font-size:14px;font-weight:600;">Item</td><td style="padding:10px 16px;">{item_name}</td></tr><tr><td style="padding:10px 16px;color:#6b7280;font-size:14px;font-weight:600;">Total</td><td style="padding:10px 16px;font-weight:bold;color:#16a34a;">{order_total}</td></tr><tr style="background:#f9fafb;"><td style="padding:10px 16px;color:#6b7280;font-size:14px;font-weight:600;">Date</td><td style="padding:10px 16px;">{order_date}</td></tr></table><p style="text-align:center;margin:32px 0;"><a href="{admin_url}" style="background:#6366f1;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">View in Admin Panel</a></p>',
                'available_shortcodes' => ['{order_id}', '{customer_name}', '{customer_email}', '{item_name}', '{order_total}', '{order_date}', '{admin_url}', '{site_name}'],
            ],
        ];

        foreach ($templates as $data) {
            EmailTemplate::withoutGlobalScopes()->updateOrCreate(
                ['key' => $data['key'], 'company_id' => null],
                [
                    'name'                 => $data['name'],
                    'subject'              => $data['subject'],
                    'body_html'            => $data['body_html'],
                    'available_shortcodes' => $data['available_shortcodes'],
                    'is_active'            => true,
                    'company_id'           => null,
                ]
            );
        }
    }
}
