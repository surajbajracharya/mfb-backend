<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = Setting::getAll();

            if (!empty($settings['timezone'])) {
                config(['app.timezone' => $settings['timezone']]);
                date_default_timezone_set($settings['timezone']);
            }

            if (!empty($settings['mail_host'])) {
                config([
                    'mail.mailers.smtp.host'       => $settings['mail_host'],
                    'mail.mailers.smtp.port'       => $settings['mail_port']       ?? 587,
                    'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?? 'tls',
                    'mail.mailers.smtp.username'   => $settings['mail_username']   ?? null,
                ]);
                if (!empty($settings['mail_password'])) {
                    config(['mail.mailers.smtp.password' => $settings['mail_password']]);
                }
            }

            if (!empty($settings['mail_from_address'])) {
                config([
                    'mail.from.address' => $settings['mail_from_address'],
                    'mail.from.name'    => $settings['mail_from_name'] ?? config('app.name'),
                ]);
            }
        } catch (\Throwable) {
            // DB not available — use defaults
        }

        return $next($request);
    }
}
