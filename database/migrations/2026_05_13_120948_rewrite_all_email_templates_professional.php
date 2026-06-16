<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $signoff = '<p style="color:#888;font-size:13px;margin-top:28px;">Warm regards,<br><strong>The {site_name} Team</strong></p>';

    private function tbl(array $rows): string
    {
        $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">';
        foreach ($rows as $i => [$label, $value]) {
            $border = ($i < count($rows) - 1) ? 'border-bottom:1px solid #e5e7eb;' : '';
            $html .= "<tr><td style=\"padding:8px 12px;background:#f3f4f6;font-weight:600;color:#374151;width:160px;\">{$label}</td><td style=\"padding:8px 12px;{$border}\">{$value}</td></tr>";
        }
        return $html . '</table>';
    }

    private function btn(string $url, string $label): string
    {
        return "<p><a href=\"{$url}\" style=\"background:#6366f1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;display:inline-block;\">{$label}</a></p>";
    }

    private function alert(string $msg, string $type = 'warning'): string
    {
        $colors = $type === 'danger'
            ? ['bg:#fef2f2', 'border:#fecaca', 'color:#b91c1c']
            : ['bg:#fffbeb', 'border:#fde68a', 'color:#b45309'];
        return "<p style=\"font-size:13px;background:{$colors[0]};border:1px solid {$colors[1]};border-radius:6px;padding:10px 14px;margin:16px 0;color:{$colors[2]};\">{$msg}</p>";
    }

    public function up(): void
    {
        $so = $this->signoff;

        $templates = [

            /* ── Account ─────────────────────────────────────────────────────── */
            'account_activated' => [
                'subject' => 'Your {site_name} account has been activated',
                'body'    => '<p>Dear {username},</p><h2>Your Account Is Now Active</h2><p>Your account on <strong>{site_name}</strong> has been successfully activated. You now have full access to all courses, content, and features available to you.</p><p>If you have any questions getting started, our support team is always here to help.</p>'
                           . $this->btn('{dashboard_url}', 'Go to Dashboard') . $so,
                'codes'   => ['{username}', '{email}', '{dashboard_url}', '{site_name}'],
            ],

            'account_deactivated' => [
                'subject' => 'Your {site_name} account has been deactivated',
                'body'    => '<p>Dear {username},</p><h2>Account Deactivated</h2><p>Your account on <strong>{site_name}</strong> has been temporarily deactivated by an administrator.</p><p>If you believe this is a mistake or would like further information, please reply to this email or contact our support team directly.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{site_name}'],
            ],

            'admin_user_created' => [
                'subject' => 'Your account on {site_name} is ready',
                'body'    => '<p>Dear {username},</p><h2>Welcome to {site_name}</h2><p>An administrator has created an account for you on <strong>{site_name}</strong>. Your login credentials are provided below.</p>'
                           . $this->tbl([['Email', '{email}'], ['Temporary Password', '{temp_password}']])
                           . $this->alert('<strong>Security Notice:</strong> Please log in and change your password immediately. Do not share your credentials with anyone.', 'warning')
                           . $this->btn('{dashboard_url}', 'Log In Now') . $so,
                'codes'   => ['{username}', '{email}', '{temp_password}', '{dashboard_url}', '{site_name}'],
            ],

            'welcome' => [
                'subject' => 'Welcome to {site_name}, {username}!',
                'body'    => '<p>Dear {username},</p><h2>Welcome to {site_name}</h2><p>We are delighted to have you join <strong>{site_name}</strong>. Your account is ready and your journey to wellness and mindfulness starts now.</p><p>Explore our courses, events, and resources — everything you need to support your growth is right here.</p>'
                           . $this->btn('{dashboard_url}', 'Start Your Journey') . $so,
                'codes'   => ['{username}', '{email}', '{site_name}', '{dashboard_url}'],
            ],

            /* ── Auth ─────────────────────────────────────────────────────────── */
            'email_verification' => [
                'subject' => 'Please verify your email address — {site_name}',
                'body'    => '<p>Dear {username},</p><h2>Verify Your Email Address</h2><p>Thank you for creating an account on <strong>{site_name}</strong>. To complete your registration, please verify your email address by clicking the button below.</p>'
                           . $this->btn('{verification_url}', 'Verify Email Address')
                           . '<p style="color:#888;font-size:13px;margin-top:16px;">This link is valid for one-time use only. If you did not create an account on {site_name}, you can safely ignore this email.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{verification_url}', '{site_name}'],
            ],

            'password_reset' => [
                'subject' => 'Reset your {site_name} password',
                'body'    => '<p>Dear {username},</p><h2>Password Reset Request</h2><p>We received a request to reset the password for your <strong>{site_name}</strong> account. Click the button below to set a new password.</p>'
                           . $this->btn('{reset_url}', 'Reset My Password')
                           . '<p style="color:#888;font-size:13px;margin-top:16px;">This link will expire in <strong>60 minutes</strong>. If you did not request a password reset, you can safely ignore this email — your account remains secure.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{reset_url}', '{site_name}'],
            ],

            'password_changed' => [
                'subject' => 'Your {site_name} password has been changed',
                'body'    => '<p>Dear {username},</p><h2>Password Changed Successfully</h2><p>This is a confirmation that the password for your <strong>{site_name}</strong> account (<strong>{email}</strong>) was successfully changed.</p>'
                           . $this->alert('<strong>Did not make this change?</strong> Please contact our support team immediately to secure your account.', 'danger') . $so,
                'codes'   => ['{username}', '{email}', '{site_name}'],
            ],

            /* ── Orders ───────────────────────────────────────────────────────── */
            'order_confirmed' => [
                'subject' => 'Order confirmation — #{order_id}',
                'body'    => '<p>Dear {username},</p><h2>Order Confirmed</h2><p>Thank you for your purchase on <strong>{site_name}</strong>. Your order has been confirmed and your access has been activated.</p>'
                           . $this->tbl([['Order No.', '#{order_id}'], ['Total', '{order_total}'], ['Date', '{order_date}']])
                           . $this->btn('{dashboard_url}', 'Go to Dashboard') . $so,
                'codes'   => ['{username}', '{email}', '{order_id}', '{order_total}', '{order_date}', '{dashboard_url}', '{site_name}'],
            ],

            'order_cancelled' => [
                'subject' => 'Your order #{order_id} has been cancelled',
                'body'    => '<p>Dear {username},</p><h2>Order Cancelled</h2><p>Your order <strong>#{order_id}</strong> has been cancelled. If a payment was made, a refund will be processed and should appear in your account within 5–10 business days.</p><p>If you believe this cancellation was made in error, please contact our support team and we will be happy to assist.</p>'
                           . $this->btn('{dashboard_url}', 'View Dashboard') . $so,
                'codes'   => ['{username}', '{email}', '{order_id}', '{dashboard_url}', '{site_name}'],
            ],

            'order_refunded' => [
                'subject' => 'Refund processed for order #{order_id}',
                'body'    => '<p>Dear {username},</p><h2>Refund Processed</h2><p>A refund has been successfully processed for your order <strong>#{order_id}</strong>.</p>'
                           . $this->tbl([['Refund Amount', '{order_total}']])
                           . '<p>Please allow 5–10 business days for the funds to appear in your account, depending on your bank or payment provider.</p><p>If you have any questions regarding your refund, please do not hesitate to contact our support team.</p>'
                           . $this->btn('{dashboard_url}', 'View Dashboard') . $so,
                'codes'   => ['{username}', '{email}', '{order_id}', '{order_total}', '{dashboard_url}', '{site_name}'],
            ],

            /* ── Subscriptions ────────────────────────────────────────────────── */
            'subscription_started' => [
                'subject' => 'Your {plan_name} subscription is now active',
                'body'    => '<p>Dear {username},</p><h2>Subscription Activated</h2><p>Your <strong>{plan_name}</strong> subscription is now active. You have access to all included courses and premium content.</p>'
                           . $this->tbl([['Plan', '{plan_name}'], ['Next Billing Date', '{next_billing_date}']])
                           . $this->btn('{dashboard_url}', 'Explore Your Content') . $so,
                'codes'   => ['{username}', '{email}', '{plan_name}', '{next_billing_date}', '{dashboard_url}', '{site_name}'],
            ],

            'subscription_cancelled' => [
                'subject' => 'Your {plan_name} subscription has been cancelled',
                'body'    => '<p>Dear {username},</p><h2>Subscription Cancelled</h2><p>Your <strong>{plan_name}</strong> subscription has been successfully cancelled.</p><p>You will continue to have access to your subscription benefits until the end of your current billing period, after which your access will revert to the standard tier.</p><p>We are sorry to see you go. If you change your mind or have any feedback for us, we would love to hear from you.</p>'
                           . $this->btn('{dashboard_url}', 'View Dashboard') . $so,
                'codes'   => ['{username}', '{email}', '{plan_name}', '{dashboard_url}', '{site_name}'],
            ],

            'subscription_expired' => [
                'subject' => 'Your {plan_name} subscription has expired',
                'body'    => '<p>Dear {username},</p><h2>Subscription Expired</h2><p>Your <strong>{plan_name}</strong> subscription has expired and your premium access has ended.</p><p>To continue enjoying all the benefits, courses, and content included in your plan, please renew your subscription at your convenience.</p>'
                           . $this->btn('{dashboard_url}', 'Renew Subscription') . $so,
                'codes'   => ['{username}', '{email}', '{plan_name}', '{dashboard_url}', '{site_name}'],
            ],

            /* ── Appointments ─────────────────────────────────────────────────── */
            'appointment_booked_pending' => [
                'subject' => 'Your {appointment_type} booking request has been received',
                'body'    => '<p>Dear {username},</p><h2>Appointment Request Received</h2><p>Thank you for your booking request. We have received your request for <strong>{appointment_type}</strong> and it is currently awaiting confirmation.</p>'
                           . $this->tbl([['Type', '{appointment_type}'], ['Requested Time', '{appointment_datetime}']])
                           . '<p>We will notify you by email once your appointment has been confirmed. In the meantime, you can track your pending bookings from your dashboard.</p>'
                           . $this->btn('{dashboard_url}', 'View My Appointments') . $so,
                'codes'   => ['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}'],
            ],

            'appointment_confirmed' => [
                'subject' => 'Your {appointment_type} appointment is confirmed',
                'body'    => '<p>Dear {username},</p><h2>Appointment Confirmed</h2><p>Your appointment has been confirmed. Please find the details below.</p>'
                           . $this->tbl([['Type', '{appointment_type}'], ['Date &amp; Time', '{appointment_datetime}']])
                           . '<p>We look forward to seeing you. If you need to reschedule or have any questions, please contact us in advance.</p>'
                           . $this->btn('{dashboard_url}', 'View My Appointments') . $so,
                'codes'   => ['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}'],
            ],

            'appointment_reminder' => [
                'subject' => 'Reminder: Your {appointment_type} appointment is coming up',
                'body'    => '<p>Dear {username},</p><h2>Upcoming Appointment Reminder</h2><p>This is a friendly reminder about your upcoming appointment on <strong>{site_name}</strong>.</p>'
                           . $this->tbl([['Type', '{appointment_type}'], ['Date &amp; Time', '{appointment_datetime}']])
                           . '<p>If you need to reschedule or have any questions, please do not hesitate to contact us.</p>'
                           . $this->btn('{dashboard_url}', 'View My Appointments') . $so,
                'codes'   => ['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}'],
            ],

            'appointment_rescheduled' => [
                'subject' => 'Your {appointment_type} appointment has been rescheduled',
                'body'    => '<p>Dear {username},</p><h2>Appointment Rescheduled</h2><p>Your <strong>{appointment_type}</strong> appointment has been rescheduled to a new date and time.</p>'
                           . $this->tbl([['Type', '{appointment_type}'], ['New Date &amp; Time', '{appointment_datetime}']])
                           . '<p>If this new time does not suit you, please contact us at your earliest convenience so we can find a more suitable arrangement.</p>'
                           . $this->btn('{dashboard_url}', 'View My Appointments') . $so,
                'codes'   => ['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{dashboard_url}', '{site_name}'],
            ],

            'appointment_cancelled' => [
                'subject' => 'Your {appointment_type} appointment has been cancelled',
                'body'    => '<p>Dear {username},</p><h2>Appointment Cancelled</h2><p>We regret to inform you that your <strong>{appointment_type}</strong> appointment scheduled for <strong>{appointment_datetime}</strong> has been cancelled.</p><p><strong>Reason:</strong> {cancellation_reason}</p><p>We apologise for any inconvenience this may have caused. You are welcome to book a new appointment at your convenience.</p>'
                           . $this->btn('{dashboard_url}', 'Book a New Appointment') . $so,
                'codes'   => ['{username}', '{email}', '{appointment_type}', '{appointment_datetime}', '{cancellation_reason}', '{dashboard_url}', '{site_name}'],
            ],

            /* ── Courses & Certificates ───────────────────────────────────────── */
            'enrollment_confirmed' => [
                'subject' => 'You are now enrolled in {course_title}',
                'body'    => '<p>Dear {username},</p><h2>Enrolment Confirmed</h2><p>You are now enrolled in <strong>{course_title}</strong>. Your access has been activated and you can begin learning at any time.</p><p>Log in whenever you are ready — we hope the course provides real value on your journey.</p>'
                           . $this->btn('{course_url}', 'Start Learning') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{course_url}', '{dashboard_url}', '{site_name}'],
            ],

            'certificate_issued' => [
                'subject' => 'Your certificate for {course_title} is ready to download',
                'body'    => '<p>Dear {username},</p><h2>Certificate of Completion</h2><p>Congratulations! You have successfully completed <strong>{course_title}</strong>. Your certificate of completion is now ready for download.</p>'
                           . $this->tbl([['Certificate No.', '{certificate_number}']])
                           . '<p>Your achievement reflects your commitment and dedication to personal growth. We are proud of what you have accomplished.</p>'
                           . $this->btn('{certificate_url}', 'Download Your Certificate') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{certificate_number}', '{certificate_url}', '{site_name}'],
            ],

            /* ── Events ───────────────────────────────────────────────────────── */
            'event_ticket' => [
                'subject' => 'Your ticket for {event_title} is confirmed',
                'body'    => '<p>Dear {username},</p><h2>Ticket Confirmed</h2><p>Your ticket for <strong>{event_title}</strong> has been confirmed. Please find your booking details below.</p>'
                           . $this->tbl([['Event', '{event_title}'], ['Date', '{event_date}'], ['Ticket No.', '{ticket_number}']])
                           . '<p>Please keep this email as your booking reference. We look forward to welcoming you to the event.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{event_title}', '{event_date}', '{ticket_number}', '{site_name}'],
            ],

            'event_cancelled' => [
                'subject' => 'Important: {event_title} has been cancelled',
                'body'    => '<p>Dear {username},</p><h2>Event Cancelled</h2><p>We regret to inform you that <strong>{event_title}</strong> scheduled for <strong>{event_date}</strong> has been cancelled.</p><p>A refund for your ticket <strong>#{ticket_number}</strong> has been initiated and should appear in your account within 5–10 business days, depending on your payment provider.</p><p>We sincerely apologise for any inconvenience this may have caused and appreciate your understanding.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{event_title}', '{event_date}', '{ticket_number}', '{site_name}'],
            ],

            'event_ticket_cancelled' => [
                'subject' => 'Your ticket for {event_title} has been cancelled',
                'body'    => '<p>Dear {username},</p><h2>Ticket Cancellation Confirmed</h2><p>Your ticket <strong>#{ticket_number}</strong> for <strong>{event_title}</strong> has been cancelled.</p><p>If you are entitled to a refund, it will be processed and should appear in your account within 5–10 business days. If you have any questions, please do not hesitate to contact our support team.</p>' . $so,
                'codes'   => ['{username}', '{email}', '{event_title}', '{ticket_number}', '{site_name}'],
            ],

            'event_published' => [
                'subject' => 'New event available: {event_title}',
                'body'    => '<p>Dear {username},</p><h2>New Event: {event_title}</h2><p>We are pleased to announce that a new event is now available on <strong>{site_name}</strong>.</p>'
                           . $this->tbl([['Event', '{event_title}'], ['Date', '{event_date}']])
                           . $this->btn('{event_url}', 'View Event Details') . $so,
                'codes'   => ['{username}', '{email}', '{event_title}', '{event_date}', '{event_url}', '{site_name}'],
            ],

            /* ── Resources ────────────────────────────────────────────────────── */
            'resource_published' => [
                'subject' => 'New resource available: {resource_title}',
                'body'    => '<p>Dear {username},</p><h2>New Resource Available</h2><p>A new resource has been published on <strong>{site_name}</strong> and is now available as part of your membership.</p>'
                           . $this->tbl([['Resource', '{resource_title}']])
                           . $this->btn('{resource_url}', 'View Resource') . $so,
                'codes'   => ['{username}', '{email}', '{resource_title}', '{resource_url}', '{site_name}'],
            ],

            /* ── Reviews ──────────────────────────────────────────────────────── */
            'review_approved' => [
                'subject' => 'Your review of {course_title} is now live',
                'body'    => '<p>Dear {username},</p><h2>Your Review Is Now Live</h2><p>Your <strong>{rating}</strong> review of <strong>{course_title}</strong> has been approved by our moderation team and is now publicly visible on the course page.</p><p>Thank you for taking the time to share your experience — your feedback genuinely helps our community make informed decisions.</p>'
                           . $this->btn('{course_url}', 'View Course') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}'],
            ],

            'review_unapproved' => [
                'subject' => 'Your review of {course_title} has been temporarily unpublished',
                'body'    => '<p>Dear {username},</p><h2>Review Temporarily Unpublished</h2><p>Your <strong>{rating}</strong> review of <strong>{course_title}</strong> has been temporarily removed from public view by our moderation team.</p><p>If you have any questions or would like to discuss this further, please contact our support team and we will be happy to assist.</p>'
                           . $this->btn('{course_url}', 'View Course') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{site_name}'],
            ],

            'review_deleted' => [
                'subject' => 'Your review of {course_title} has been removed',
                'body'    => '<p>Dear {username},</p><h2>Review Removed</h2><p>Your review of <strong>{course_title}</strong> has been reviewed by our moderation team and has been removed from our platform.</p><p>We take the quality of our community content seriously. If you believe this decision was made in error, please contact our support team and we will look into it further.</p>'
                           . $this->btn('{course_url}', 'View Course') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{course_url}', '{site_name}'],
            ],

            'review_replied' => [
                'subject' => '{site_name} replied to your review of {course_title}',
                'body'    => '<p>Dear {username},</p><h2>Response to Your Review</h2><p>The team at <strong>{site_name}</strong> has responded to your <strong>{rating}</strong> review of <strong>{course_title}</strong>.</p><p style="font-weight:600;color:#374151;margin-bottom:6px;">Their response:</p><blockquote style="border-left:4px solid #6366f1;padding:12px 18px;color:#555;margin:0 0 20px;background:#f9f9ff;border-radius:0 6px 6px 0;font-style:italic;">{admin_reply}</blockquote>'
                           . $this->btn('{course_url}', 'View Course') . $so,
                'codes'   => ['{username}', '{email}', '{course_title}', '{course_url}', '{rating}', '{admin_reply}', '{site_name}'],
            ],

        ];

        foreach ($templates as $key => $t) {
            DB::table('email_templates')
                ->where('key', $key)
                ->update([
                    'subject'              => $t['subject'],
                    'body_html'            => $t['body'],
                    'available_shortcodes' => json_encode($t['codes']),
                    'updated_at'           => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty — rolling back email templates is not safe
    }
};
