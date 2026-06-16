<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestAuthController extends Controller
{
    /**
     * Find or create a guest user by email and return a Sanctum token.
     * If a full (non-guest) account exists for this email, return 409 so
     * the frontend can prompt the user to sign in instead.
     */
    public function guestCheckout(Request $request): JsonResponse
    {
        $companyId = app(TenantContext::class)->companyId();

        $data = $request->validate([
            'name'           => ['nullable', 'string', 'max:255'],
            'email'          => ['required', 'string', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'date_of_birth'  => ['nullable', 'date'],
            'time_of_birth'  => ['nullable', 'string', 'max:10'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
        ]);

        // Look for an existing account with this email for this company
        $existing = User::withoutGlobalScope('company')
            ->where('email', $data['email'])
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->first();

        if ($existing) {
            // Whether a real account or a prior guest — issue a checkout token so the
            // order is stored under their account. The frontend discards the token after
            // payment and never starts a login session.
            $existing->tokens()->where('name', 'guest_checkout_token')->delete();
            $token = $existing->createToken('guest_checkout_token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => $existing,
            ]);
        }

        // Create a new guest user with a real auto-generated password
        $plainPassword = Str::random(10) . rand(10, 99);
        $name = $data['name'] ?? Str::before($data['email'], '@');

        $user = User::create([
            'name'              => $name,
            'email'             => $data['email'],
            'password'          => Hash::make($plainPassword),
            'phone'             => $data['phone'] ?? null,
            'date_of_birth'     => $data['date_of_birth'] ?? null,
            'time_of_birth'     => $data['time_of_birth'] ?? null,
            'place_of_birth'    => $data['place_of_birth'] ?? null,
            'is_guest'          => true,
            'email_verified_at' => now(),
            'gdpr_consent'      => true,
            'gdpr_consent_at'   => now(),
            'company_id'        => $companyId,
            'source'            => 'guest_checkout',
        ]);

        $user->assignRole('user');

        // Send welcome email with login credentials
        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $loginUrl    = $frontendUrl . '/login';
        $siteName    = Setting::getValue('site_name') ?? config('app.name');

        EmailService::send($user->email, 'guest_checkout_welcome', [
            '{username}'      => $name,
            '{email}'         => $user->email,
            '{login_url}'     => $loginUrl,
            '{temp_password}' => $plainPassword,
            '{site_name}'     => $siteName,
        ]);

        $token = $user->createToken('guest_checkout_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ], 201);
    }
}
