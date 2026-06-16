<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use App\Services\TenantContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $companyId = app(TenantContext::class)->companyId();

        $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => [
                'required', 'string', 'email', 'max:255',
                // Allow registration if the only existing account is a guest account
                Rule::unique('users')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('is_guest', false)
                ),
            ],
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
            'gdpr_consent' => ['required', 'boolean', 'accepted'],
        ], [
            'email.unique' => 'This email is already registered. Please reset your password to sign in.',
        ]);

        // Upgrade guest account if one exists for this email
        $guest = User::withoutGlobalScope('company')
            ->where('email', $request->email)
            ->where('company_id', $companyId)
            ->where('is_guest', true)
            ->first();

        if ($guest) {
            $guest->tokens()->delete();
            $guest->update([
                'name'            => $request->name,
                'password'        => Hash::make($request->password),
                'is_guest'        => false,
                'gdpr_consent'    => true,
                'gdpr_consent_at' => now(),
                'source'          => 'self_registered',
            ]);
            $user = $guest->fresh();
        } else {
            $user = User::create([
                'name'            => $request->name,
                'email'           => $request->email,
                'password'        => Hash::make($request->password),
                'gdpr_consent'    => true,
                'gdpr_consent_at' => now(),
                'company_id'      => $companyId,
                'source'          => 'self_registered',
            ]);
            $user->assignRole('user');
        }

        // Generate a verification token and store it
        $verificationToken = Str::random(64);
        $user->forceFill(['remember_token' => $verificationToken])->save();

        $company         = app(\App\Services\TenantContext::class)->getCompany();
        if ($company) {
            $scheme      = app()->environment('local') ? 'http' : 'https';
            $frontendUrl = rtrim($scheme . '://' . $company->domain, '/');
        } else {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');
        }
        $verificationUrl = $frontendUrl . '/verify-email?token=' . $verificationToken . '&email=' . urlencode($user->email);
        $siteName        = Setting::getValue('site_name', config('app.name'));

        // Send email verification
        EmailService::send($user->email, 'email_verification', [
            '{username}'         => $user->name,
            '{email}'            => $user->email,
            '{verification_url}' => $verificationUrl,
            '{site_name}'        => $siteName,
        ]);

        // Send welcome email
        EmailService::send($user->email, 'welcome', [
            '{username}'      => $user->name,
            '{email}'         => $user->email,
            '{site_name}'     => $siteName,
            '{dashboard_url}' => $frontendUrl . '/dashboard',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    /**
     * Admin panel login — not scoped by company. Supports both global super-admins
     * (company_id = null) and company-specific admins (company_id = X).
     * The frontend sets X-Company-ID automatically from user.company_id after login.
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScope('company')
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Please verify your email address before logging in.'], 403);
        }

        // Any role other than 'user' (customer) grants admin panel access — matches frontend isAdmin() logic.
        $isAdmin = $user->roles->isNotEmpty() && !$user->roles->every(fn ($r) => $r->name === 'user');
        if (!$isAdmin) {
            return response()->json(['message' => 'Access denied. This portal is for administrators only.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        DB::table('users')->where('id', $user->id)->update(['is_online' => true]);

        return response()->json([
            'token' => $token,
            'user'  => $user->load('roles'),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $companyId = app(TenantContext::class)->companyId();

        // Find user scoped strictly to this company.
        // Global admins (company_id = null) are allowed on any site (admin panel).
        // A company user can ONLY log in on their own company's site.
        $user = User::withoutGlobalScope('company')
            ->where('email', $request->email)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            })
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Please verify your email address before logging in.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        DB::table('users')->where('id', $user->id)->update(['is_online' => true]);

        return response()->json([
            'token' => $token,
            'user'  => $user->load('roles'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        DB::table('users')->where('id', $user->id)->update(['is_online' => false]);
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'company']);
        $data = $user->toArray();
        // Include flat permission list so the frontend can gate menu/actions
        $data['permissions'] = $user->getAllPermissions()->pluck('name')->values();
        return response()->json($data);
    }

    public function requestAccountDeletion(Request $request): JsonResponse
    {
        $user      = $request->user();
        $adminUser = \App\Models\User::role('super_admin')->first();
        $adminEmail = $adminUser?->email ?? config('mail.from.address', 'admin@meditationforbeginners.com');
        $siteName  = \App\Models\Setting::getValue('site_name') ?? config('app.name');
        $adminUrl  = rtrim(config('app.admin_url', 'http://localhost:3010'), '/');

        $shortcodes = [
            '{username}'     => $user->name,
            '{email}'        => $user->email,
            '{user_id}'      => $user->id,
            '{requested_at}' => now()->format('d M Y, H:i'),
            '{admin_url}'    => $adminUrl . '/admin/users/' . $user->id,
            '{site_name}'    => $siteName,
        ];

        // Notify admin
        \App\Services\EmailService::send($adminEmail, 'account_deletion_request', $shortcodes);

        // Confirm to the user
        \App\Services\EmailService::send($user->email, 'account_deletion_request_user', $shortcodes);

        return response()->json(['message' => 'Your deletion request has been sent to our team.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name'                  => ['sometimes', 'string', 'max:255'],
            'email'                 => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'                 => ['sometimes', 'nullable', 'string'],
            'bio'                   => ['sometimes', 'nullable', 'string'],
            'avatar'                => ['sometimes', 'nullable', 'string'],
            'interests'             => ['sometimes', 'nullable', 'array'],
            'interests.*'           => ['string', 'max:100'],
            'experience_level'      => ['sometimes', 'nullable', 'in:beginner,intermediate,experienced'],
            'billing_first_name'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_last_name'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_suburb'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_state'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'billing_postcode'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'billing_country'       => ['sometimes', 'nullable', 'string', 'max:2'],
        ];

        $request->validate($rules);

        $fields = ['name', 'phone', 'bio', 'avatar', 'interests', 'experience_level',
            'billing_first_name', 'billing_last_name',
            'billing_address_line1', 'billing_address_line2',
            'billing_suburb', 'billing_state', 'billing_postcode', 'billing_country'];

        // Allow email update only when it has actually changed (and only super_admin can change it)
        if ($request->has('email') && $request->email !== $user->email) {
            if (!$user->hasRole('super_admin')) {
                return response()->json(['message' => 'You are not allowed to change your email.'], 403);
            }
            $fields[] = 'email';
        }

        $user->update($request->only($fields));

        return response()->json(['message' => 'Profile updated.', 'user' => $user]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if it was one we stored
        if ($user->avatar) {
            $oldPath = ltrim(parse_url($user->avatar, PHP_URL_PATH), '/');
            $oldPath = str_replace('storage/', '', $oldPath);
            if (str_starts_with($oldPath, 'uploads/avatars/')) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $file     = $request->file('avatar');
        $filename = 'user_' . $user->id . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('uploads/avatars', $filename, 'public');
        $url      = asset('storage/' . $path);

        $user->update(['avatar' => $url]);

        return response()->json(['avatar' => $url, 'user' => $user]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        EmailService::send($user->email, 'password_changed', [
            '{username}'  => $user->name,
            '{email}'     => $user->email,
            '{site_name}' => AppModelsSetting::getValue('site_name', config('app.name')),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Only send reset link if user belongs to this company
        $companyId = app(TenantContext::class)->companyId();
        $exists = User::withoutGlobalScope('company')
            ->where('email', $request->email)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->exists();

        // Always return the same message to avoid user enumeration
        if (!$exists) {
            return response()->json(['message' => __('passwords.sent')]);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    /**
     * Verify email via token + email from the verification link.
     * This is a public route — no auth required.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        // Use withoutCompanyScope so the link works regardless of which company header is sent
        $user = User::withoutCompanyScope()
                    ->where('email', $request->email)
                    ->where('remember_token', $request->token)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        $user->forceFill(['remember_token' => null])->save();
        event(new Verified($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    public function getPrivacyPreferences(Request $request): JsonResponse
    {
        $user     = $request->user();
        $saved    = $user->privacy_preferences ?? [];
        $defaults = [
            'public_profile'    => false,
            'show_progress'     => false,
            'show_certificates' => false,
        ];
        return response()->json(['data' => array_merge($defaults, $saved)]);
    }

    public function updatePrivacyPreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_profile'    => ['sometimes', 'boolean'],
            'show_progress'     => ['sometimes', 'boolean'],
            'show_certificates' => ['sometimes', 'boolean'],
        ]);
        $user    = $request->user();
        $current = $user->privacy_preferences ?? [];
        $user->update(['privacy_preferences' => array_merge($current, $data)]);
        return response()->json(['data' => $user->fresh()->privacy_preferences]);
    }

    public function publicProfile(Request $request, string $username): JsonResponse
    {
        $user = User::withoutGlobalScope('company')
            ->select('id', 'name', 'username', 'avatar', 'bio', 'interests', 'experience_level',
                     'created_at', 'is_identity_verified', 'privacy_preferences')
            ->where('username', $username)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $priv = array_merge(
            ['public_profile' => false, 'show_progress' => false, 'show_certificates' => false],
            $user->privacy_preferences ?? []
        );

        if (!$priv['public_profile']) {
            return response()->json(['message' => 'This profile is private.'], 403);
        }

        $profile = [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'avatar'               => $user->avatar,
            'bio'                  => $user->bio,
            'interests'            => $user->interests,
            'experience_level'     => $user->experience_level,
            'member_since'         => $user->created_at?->format('F Y'),
            'is_identity_verified' => $user->is_identity_verified,
        ];

        if ($priv['show_progress']) {
            $profile['enrollments'] = \App\Models\Enrollment::where('user_id', $user->id)
                ->with('course:id,title,slug,thumbnail')
                ->get()
                ->map(fn($e) => [
                    'course'            => $e->course,
                    'progress_percent'  => $e->progress_percent ?? 0,
                    'completed_lectures'=> $e->completed_lectures ?? 0,
                    'total_lectures'    => $e->total_lectures ?? 0,
                ]);
        }

        if ($priv['show_certificates']) {
            $profile['certificates'] = \App\Models\Certificate::where('user_id', $user->id)
                ->with('course:id,title,slug')
                ->get()
                ->map(fn($c) => [
                    'certificate_number' => $c->certificate_number,
                    'issued_at'          => $c->issued_at,
                    'course'             => $c->course,
                ]);
        }

        // Always include earned challenge badges (public achievement)
        $profile['challenge_badges'] = \App\Models\ChallengeEnrollment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('badge_awarded', true)
            ->with('challenge:id,title,badge_image')
            ->get()
            ->filter(fn($e) => $e->challenge)
            ->map(fn($e) => [
                'title'       => $e->challenge->title,
                'badge_image' => $e->challenge->badge_image,
                'completed_at'=> $e->completed_at,
            ])
            ->values();

        return response()->json(['data' => $profile]);
    }

    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user     = $request->user();
        $saved    = $user->notification_preferences ?? [];
        $defaults = [
            'course_updates'        => true,
            'new_content'           => true,
            'appointment_reminders' => true,
            'newsletter'            => false,
            'promotional'           => false,
        ];

        return response()->json(['data' => array_merge($defaults, $saved)]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_updates'        => ['sometimes', 'boolean'],
            'new_content'           => ['sometimes', 'boolean'],
            'appointment_reminders' => ['sometimes', 'boolean'],
            'newsletter'            => ['sometimes', 'boolean'],
            'promotional'           => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $current = $user->notification_preferences ?? [];
        $user->update(['notification_preferences' => array_merge($current, $data)]);

        return response()->json(['data' => $user->fresh()->notification_preferences]);
    }
}
