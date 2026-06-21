<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetResponseService
{
    private const API_BASE = 'https://api.getresponse.com/v3';

    /**
     * Add a contact to a GetResponse list.
     * Uses the API key and list ID stored on the appointment type.
     * Silently logs on failure — never throws, so a failed sync never breaks a booking.
     */
    public static function addContact(string $email, string $name, string $apiKey, string $listId): void
    {
        if (!$apiKey || !$listId) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => 'api-key ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_BASE . '/contacts', [
                'email'    => $email,
                'name'     => $name,
                'campaign' => ['campaignId' => $listId],
            ]);

            // 409 = contact already exists in the list — not an error
            if (!$response->successful() && $response->status() !== 409) {
                Log::warning('GetResponse addContact failed', [
                    'email'  => $email,
                    'listId' => $listId,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('GetResponse addContact exception: ' . $e->getMessage());
        }
    }
}
