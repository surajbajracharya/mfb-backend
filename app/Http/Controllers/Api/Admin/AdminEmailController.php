<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEmailController extends Controller
{
    // ── Templates ─────────────────────────────────────────────────────────────

    public function templates(): JsonResponse
    {
        return response()->json(['data' => EmailTemplate::orderBy('id')->get()]);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'subject'   => ['sometimes', 'string', 'max:255'],
            'body_html' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template->update($data);

        return response()->json($template);
    }

    public function previewTemplate(Request $request, int $id): JsonResponse
    {
        $template = EmailTemplate::findOrFail($id);
        $settings = $this->resolvedSettings();

        $samples  = $this->sampleShortcodes($settings['site_name']);

        $keys   = array_keys($samples);
        $values = array_values($samples);

        return response()->json([
            'subject'   => str_replace($keys, $values, $template->subject),
            'body_html' => str_replace($keys, $values, $template->body_html),
            'settings'  => $settings,
        ]);
    }

    public function sendTest(Request $request, int $id): JsonResponse
    {
        $data     = $request->validate(['email' => ['required', 'email']]);
        $template = EmailTemplate::findOrFail($id);
        $settings = $this->resolvedSettings();

        $samples = array_merge(
            $this->sampleShortcodes($settings['site_name']),
            ['{email}' => $data['email']]
        );

        EmailService::send($data['email'], $template->key, $samples);

        return response()->json(['message' => "Test email sent to {$data['email']}."]);
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function getSettings(): JsonResponse
    {
        // Return the resolved settings so the frontend preview reflects the correct branding.
        return response()->json($this->resolvedSettings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = EmailSetting::getSettings();

        $data = $request->validate([
            'site_name'         => ['sometimes', 'string', 'max:255'],
            'from_name'         => ['sometimes', 'string', 'max:255'],
            'from_email'        => ['sometimes', 'email', 'max:255'],
            'header_logo'       => ['sometimes', 'nullable', 'string'],
            'header_bg_color'   => ['sometimes', 'string', 'max:20'],
            'header_text_color' => ['sometimes', 'string', 'max:20'],
            'header_tagline'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'footer_html'       => ['sometimes', 'nullable', 'string'],
            'footer_bg_color'   => ['sometimes', 'string', 'max:20'],
            'footer_text_color' => ['sometimes', 'string', 'max:20'],
            'social_links'      => ['sometimes', 'nullable', 'array'],
            'social_links.*'    => ['nullable', 'string', 'max:255'],
        ]);

        $settings->update($data);

        return response()->json($this->resolvedSettings());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build the email settings array with identity values resolved from the
     * company's Settings panel — mirrors EmailService::send() exactly so that
     * preview and test emails always reflect the same branding as real emails.
     */
    private function resolvedSettings(): array
    {
        $settings = EmailSetting::getSettings()->toArray();

        // from_name: explicit mail_from_name → site_name → company_name → stored value
        $settings['from_name'] = Setting::getValue('mail_from_name')
                              ?? Setting::getValue('site_name')
                              ?? Setting::getValue('company_name')
                              ?? $settings['from_name'];

        // site_name used in header/footer display
        $settings['site_name'] = Setting::getValue('site_name')
                              ?? Setting::getValue('company_name')
                              ?? $settings['site_name'];

        // from_email: only override when the company has configured one
        $smtpFromAddress = Setting::getValue('mail_from_address');
        if ($smtpFromAddress) {
            $settings['from_email'] = $smtpFromAddress;
        }

        // header_logo: fall back to company logo from general settings
        if (empty($settings['header_logo'])) {
            $logo = Setting::getValue('company_logo');
            if ($logo) {
                $settings['header_logo'] = $logo;
            }
        }

        return $settings;
    }

    /**
     * Sample shortcode values used for template preview and test sends.
     */
    private function sampleShortcodes(string $siteName): array
    {
        $company     = app(\App\Services\TenantContext::class)->getCompany();
        $scheme      = app()->environment('local') ? 'http' : 'https';
        $frontendUrl = $company
            ? rtrim($scheme . '://' . $company->domain, '/')
            : rtrim(config('app.frontend_url', url('/')), '/');
        $adminUrl    = rtrim(env('ADMIN_URL', config('app.url')), '/');

        return [
            '{username}'             => 'Jane Doe',
            '{email}'                => 'jane@example.com',
            '{site_name}'            => $siteName,
            '{login_url}'            => $adminUrl . '/admin/login',
            '{dashboard_url}'        => $frontendUrl . '/dashboard',
            '{verification_url}'     => $frontendUrl . '/verify-email?token=sample&email=jane%40example.com',
            '{reset_url}'            => $frontendUrl . '/reset-password?token=sample&email=jane%40example.com',
            '{order_id}'             => '1042',
            '{order_total}'          => '$149.00',
            '{order_date}'           => now()->format('F j, Y'),
            '{course_title}'         => 'Mindfulness Meditation Mastery',
            '{course_url}'           => $frontendUrl . '/courses/mindfulness-meditation-mastery',
            '{certificate_number}'   => now()->format('Y-m-d') . '-7',
            '{certificate_url}'      => $frontendUrl . '/dashboard/certificates',
            '{appointment_type}'     => 'One-on-One Coaching',
            '{appointment_datetime}' => now()->addDays(3)->format('l, F j Y \a\t g:i A'),
            '{event_title}'          => 'Annual Wellness Summit',
            '{event_date}'           => now()->addDays(14)->format('l, F j Y \a\t g:i A'),
            '{ticket_number}'        => 'TKT-ABCD-1234',
            '{plan_name}'            => 'Premium Membership',
            '{next_billing_date}'    => now()->addMonth()->format('F j, Y'),
            '{rating}'               => '5/5 ★★★★★',
            '{comment}'              => 'This course changed my life. Highly recommended!',
            '{admin_reviews_url}'    => config('app.url') . '/admin/reviews',
            '{cancellation_reason}'  => 'Scheduling conflict',
            '{admin_reply}'          => 'Thank you for your feedback! We\'re glad you enjoyed the course. Please feel free to explore our other offerings.',
            '{order_total}'          => '$149.00',
            '{temp_password}'        => 'TempPass@1234',
            '{resource_title}'       => 'Beginner\'s Guide to Breathwork',
            '{resource_url}'         => $frontendUrl . '/resources/beginners-guide-to-breathwork',
            '{event_url}'            => $frontendUrl . '/events/annual-wellness-summit',
        ];
    }
}
