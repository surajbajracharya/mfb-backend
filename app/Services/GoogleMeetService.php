<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMeetService
{
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function buildJwt(array $credentials): string
    {
        $now = time();

        $header  = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signingInput = "{$header}.{$payload}";
        openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        return "{$signingInput}." . $this->base64UrlEncode($signature);
    }

    private function getAccessToken(): ?string
    {
        $json = \App\Models\Setting::getValue('google_service_account_json')
              ?: config('services.google.service_account_json', '');

        if (!$json) {
            return null;
        }

        $credentials = json_decode($json, true);
        if (!$credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            return null;
        }

        $jwt = $this->buildJwt($credentials);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (!$response->ok()) {
            Log::warning('GoogleMeetService: token request failed — ' . $response->body());
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Create a Google Calendar event with a Meet link attached.
     * Credentials and calendar ID are read from admin settings — never hardcoded.
     *
     * The admin must share their Google Calendar with the service account email
     * (give "Make changes to events" permission) and paste the Calendar ID in settings.
     */
    public function createMeetLink(): ?string
    {
        try {
            $calendarId = \App\Models\Setting::getValue('google_calendar_id');
            if (!$calendarId) {
                Log::info('GoogleMeetService: google_calendar_id not configured — skipping.');
                return null;
            }

            $token = $this->getAccessToken();
            if (!$token) {
                Log::info('GoogleMeetService: no credentials configured — skipping Meet link creation.');
                return null;
            }

            $now    = now();
            $end    = $now->copy()->addHour();

            $response = Http::withToken($token)
                ->post(
                    "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events?conferenceDataVersion=1",
                    [
                        'summary' => 'Appointment',
                        'start'   => ['dateTime' => $now->toRfc3339String(), 'timeZone' => 'UTC'],
                        'end'     => ['dateTime' => $end->toRfc3339String(),  'timeZone' => 'UTC'],
                        'conferenceData' => [
                            'createRequest' => [
                                'requestId' => uniqid('meet_', true),
                            ],
                        ],
                    ]
                );

            if (!$response->ok()) {
                Log::warning('GoogleMeetService: calendar event creation failed — ' . $response->body());
                return null;
            }

            return $response->json('hangoutLink');
        } catch (\Throwable $e) {
            Log::warning('GoogleMeetService: exception — ' . $e->getMessage());
            return null;
        }
    }
}
