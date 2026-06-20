<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Maps email template keys to the user notification preference key.
     * Templates not listed here are always sent (transactional).
     */
    private const PREF_MAP = [
        'enrollment_confirmed'       => 'course_updates',
        'certificate_issued'         => 'course_updates',
        'appointment_confirmed'      => 'appointment_reminders',
        'appointment_reminder'       => 'appointment_reminders',
        'event_ticket'               => 'new_content',
        'subscription_started'       => 'promotional',
        'newsletter'                 => 'newsletter',
    ];

    /**
     * Default preferences when a user has no saved preferences (opt-in for important, opt-out for marketing).
     */
    private const DEFAULTS = [
        'course_updates'        => true,
        'new_content'           => true,
        'appointment_reminders' => true,
        'newsletter'            => false,
        'promotional'           => false,
    ];

    /**
     * Check whether a user has opted in for a given template.
     * Transactional templates (not in PREF_MAP) always return true.
     */
    public static function isAllowed(string $toEmail, string $templateKey): bool
    {
        $prefKey = self::PREF_MAP[$templateKey] ?? null;
        if ($prefKey === null) {
            return true; // transactional — always send
        }

        // withoutGlobalScopes so BelongsToCompany doesn't hide cross-tenant users
        $user = User::withoutGlobalScopes()->where('email', $toEmail)->first();
        if (!$user) {
            return true; // unknown user, send anyway
        }

        $prefs = $user->notification_preferences ?? [];
        return (bool) ($prefs[$prefKey] ?? self::DEFAULTS[$prefKey]);
    }

    /**
     * Send a templated email, queued for async delivery.
     * Respects the user's notification preferences — skips silently if opted out.
     *
     * @param  string  $toEmail
     * @param  string  $templateKey  e.g. 'welcome', 'order_confirmed'
     * @param  array   $shortcodes   e.g. ['{username}' => 'John', '{order_id}' => '123']
     */
    public static function send(string $toEmail, string $templateKey, array $shortcodes = []): void
    {
        // Check notification preference before doing any work
        if (!static::isAllowed($toEmail, $templateKey)) {
            Log::info("EmailService: '{$templateKey}' skipped for '{$toEmail}' (user preference).");
            return;
        }
        try {
            $template = EmailTemplate::findByKey($templateKey);
            if (!$template) {
                Log::warning("EmailService: template '{$templateKey}' not found or inactive.");
                return;
            }

            $settings = EmailSetting::getSettings()->toArray();

            // site_name: always use the live settings table value as source of truth
            // (email_settings.site_name can be stale from initial creation).
            $liveSiteName = Setting::getValue('site_name') ?? Setting::getValue('company_name');
            if ($liveSiteName) {
                $settings['site_name'] = $liveSiteName;
            } elseif (empty($settings['site_name'])) {
                $cid = $settings['company_id'] ?? null;
                if ($cid) {
                    $q = Setting::withoutGlobalScope('company')->where('company_id', $cid)->whereNotNull('value');
                    $settings['site_name'] = (clone $q)->where('key', 'site_name')->value('value')
                                          ?? (clone $q)->where('key', 'company_name')->value('value');
                }
            }

            // from_name: explicit setting → site_name → company_name → companies table → existing default.
            $companyName = null;
            if (!empty($settings['company_id'])) {
                $companyName = \Illuminate\Support\Facades\DB::table('companies')
                    ->where('id', $settings['company_id'])
                    ->value('name');
            }
            $settings['from_name'] = Setting::getValue('mail_from_name')
                                  ?? Setting::getValue('site_name')
                                  ?? Setting::getValue('company_name')
                                  ?? $companyName
                                  ?? $settings['from_name'];

            // from_email: explicit setting → company domain → existing default.
            $fromEmail = Setting::getValue('mail_from_address');
            if (!$fromEmail && !empty($settings['company_id'])) {
                $domain = \Illuminate\Support\Facades\DB::table('companies')
                    ->where('id', $settings['company_id'])
                    ->value('domain');
                $host = $domain ? (parse_url('http://' . $domain, PHP_URL_HOST) ?? null) : null;
                if ($host && !in_array($host, ['localhost', '127.0.0.1'])) {
                    $fromEmail = 'noreply@' . $host;
                }
            }
            if ($fromEmail) {
                $settings['from_email'] = $fromEmail;
            }

            // Use company logo as header logo when the email settings record has none set.
            if (empty($settings['header_logo'])) {
                $companyLogo = Setting::getValue('company_logo');
                if ($companyLogo) {
                    $settings['header_logo'] = $companyLogo;
                }
            }

            // Fix any localhost URLs in header_logo so email clients can load the image.
            if (!empty($settings['header_logo'])) {
                $settings['header_logo'] = preg_replace(
                    '#https?://(127\.0\.0\.1|localhost)(:\d+)?/#',
                    rtrim(config('app.url'), '/') . '/',
                    $settings['header_logo']
                );
            }

            // Always resolve {site_name} from the database — never from config or caller
            $shortcodes['{site_name}'] = $settings['site_name'] ?? config('app.name');

            // Replace shortcodes in subject and body
            $keys    = array_keys($shortcodes);
            $values  = array_values($shortcodes);
            $subject = str_replace($keys, $values, $template->subject);
            $body    = str_replace($keys, $values, $template->body_html);

            // Resolve the email header background color.
            // Priority: email_settings.header_bg_color (email-specific setting)
            //         → settings.primary_color (company brand color)
            //         → #6366f1 (built-in default)
            $brandColor = (!empty($settings['header_bg_color']) ? $settings['header_bg_color'] : null)
                       ?? Setting::getValue('primary_color')
                       ?? '#6366f1';

            // Push brand color into the settings array so the Blade template
            // uses it for the email header background and link color as well.
            $settings['header_bg_color'] = $brandColor;

            // Replace every hardcoded #6366f1 in the body with the company color.
            // Covers button backgrounds, blockquote left-borders, and any accent uses.
            if ($brandColor !== '#6366f1') {
                $body = str_replace('#6366f1', $brandColor, $body);
            }

            // Resolve SMTP config now (company context is available in this request process).
            // Passing it to the job means the queue worker never needs DB access for SMTP.
            $smtpConfig = static::resolveSmtpConfig($settings);

            \App\Jobs\SendTemplatedEmail::dispatch($toEmail, $subject, $body, $settings, $smtpConfig);
        } catch (\Throwable $e) {
            Log::error("EmailService: failed to send '{$templateKey}' to '{$toEmail}': " . $e->getMessage());
        }
    }

    /**
     * Read a setting value bypassing the company scope.
     * Used for SMTP settings which must be readable in any context (global admin, queue worker).
     * Prefers the current company's value when available, falls back to any company.
     */
    private static function smtpSetting(string $key, mixed $default = null): mixed
    {
        $companyId = null;
        try {
            $companyId = app(\App\Services\TenantContext::class)->companyId();
        } catch (\Throwable) {}

        $q = Setting::withoutGlobalScope('company')->where('key', $key);

        if ($companyId) {
            // Prefer current company's value
            $value = (clone $q)->where('company_id', $companyId)->value('value');
            if ($value !== null) return $value;
        }

        // Fallback: any row with this key (covers global admin / no company context)
        return $q->whereNotNull('value')->orderByDesc('company_id')->value('value') ?? $default;
    }

    /**
     * Build the SMTP config array from DB settings.
     * Bypasses company scope so it works in global-admin and queue-worker contexts.
     * The resolved array is passed to SendTemplatedEmail so the worker never needs DB access.
     */
    public static function resolveSmtpConfig(array $emailSettings = []): array
    {
        $host       = static::smtpSetting('mail_host');
        $encryption = strtolower((string) static::smtpSetting('mail_encryption', 'tls'));
        $scheme     = match ($encryption) {
            'ssl'  => 'smtps',
            default => 'smtp',
        };

        $fromAddress = static::smtpSetting('mail_from_address')
                    ?? $emailSettings['from_email']
                    ?? null;
        $fromName    = static::smtpSetting('mail_from_name')
                    ?? static::smtpSetting('site_name')
                    ?? static::smtpSetting('company_name')
                    ?? $emailSettings['from_name']
                    ?? config('app.name');

        return [
            'host'         => $host,
            'port'         => (int) static::smtpSetting('mail_port', 587),
            'scheme'       => $scheme,
            'username'     => static::smtpSetting('mail_username'),
            'password'     => static::smtpSetting('mail_password'),
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
        ];
    }

    /**
     * Apply a pre-resolved SMTP config array directly to Laravel's mail config.
     * Used by SendTemplatedEmail job so no DB access is needed in the worker.
     */
    public static function applySmtpConfig(array $smtpConfig): void
    {
        if (empty($smtpConfig['host'])) {
            return;
        }

        config([
            'mail.default'               => 'smtp',
            'mail.mailers.smtp.host'     => $smtpConfig['host'],
            'mail.mailers.smtp.port'     => $smtpConfig['port'],
            'mail.mailers.smtp.scheme'   => $smtpConfig['scheme'],
            'mail.mailers.smtp.username' => $smtpConfig['username'],
            'mail.mailers.smtp.password' => $smtpConfig['password'],
            'mail.from.address'          => $smtpConfig['from_address'],
            'mail.from.name'             => $smtpConfig['from_name'],
        ]);

        app('mail.manager')->purge('smtp');
    }

    /**
     * Apply SMTP settings from DB at request time (legacy / test-email usage).
     */
    public static function applySmtpSettings(): void
    {
        static::applySmtpConfig(static::resolveSmtpConfig());
    }
}
