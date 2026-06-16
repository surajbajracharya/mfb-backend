<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;


class IdentityVerificationController extends Controller
{
    // Authenticated user: get their own verification status
    public function myStatus(Request $request): JsonResponse
    {
        $v = IdentityVerification::where('user_id', $request->user()->id)->latest()->first();
        return response()->json(['data' => $v]);
    }

    // Authenticated user: self-submit ID proof (no admin link required — user is already logged in)
    public function selfSubmit(Request $request): JsonResponse
    {
        $user = $request->user();

        // Don't allow re-submission if already verified
        if ($user->is_identity_verified) {
            return response()->json(['message' => 'Your identity is already verified.'], 409);
        }

        // Check for a recent submitted (pending review) verification — don't allow spam
        $existing = IdentityVerification::where('user_id', $user->id)
            ->where('status', 'submitted')
            ->latest()
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Your ID is already submitted and under review. Please wait for our team to process it.'], 409);
        }

        $request->validate([
            'id_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $path = $request->file('id_proof')->store('identity-proofs/' . $user->id);

        IdentityVerification::create([
            'user_id'      => $user->id,
            'token'        => bin2hex(random_bytes(32)),
            'id_proof_url' => $path,
            'status'       => 'submitted',
            'used_at'      => now(),
        ]);

        return response()->json(['message' => 'Your identity document has been submitted. Our team will review it and update your profile within 24–48 hours.']);
    }

    // Admin: manually approve identity verification (sets is_identity_verified = true)
    public function adminApprove(string $userId): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($userId);
        $user->forceFill(['is_identity_verified' => true])->save();

        return response()->json(['message' => 'Identity verified.', 'is_identity_verified' => true]);
    }

    // Admin: revoke identity verification
    public function adminRevoke(string $userId): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($userId);
        $user->forceFill(['is_identity_verified' => false])->save();
        return response()->json(['message' => 'Verification revoked.', 'is_identity_verified' => false]);
    }

    // Admin: download ID proof document (authenticated, streams private file)
    public function downloadDocument(Request $request, string $id): Response|\Illuminate\Http\RedirectResponse
    {
        $v = IdentityVerification::withoutGlobalScope('company')->findOrFail($id);

        $path = $v->id_proof_url;
        if (!$path) abort(404, 'No document uploaded.');

        // Old records stored full public URLs — redirect to them (still works)
        if (str_starts_with($path, 'http')) {
            return redirect($path);
        }

        // New records store a private storage path
        if (!Storage::exists($path)) abort(404, 'Document not found.');

        $mime = Storage::mimeType($path) ?: 'application/octet-stream';
        $ext  = pathinfo($path, PATHINFO_EXTENSION);

        return response(Storage::get($path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="id-proof.' . $ext . '"',
        ]);
    }

    // Admin: send a one-time verification link to the user's email
    public function sendLink(string $userId): JsonResponse
    {
        $user = User::withoutGlobalScope('company')->findOrFail($userId);

        // Expire any existing pending verifications for this user
        IdentityVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['expires_at' => now()]);

        $token = bin2hex(random_bytes(32));

        IdentityVerification::create([
            'user_id'    => $user->id,
            'token'      => $token,
            'status'     => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $verifyUrl   = $frontendUrl . '/verify-identity/' . $token;

        EmailService::send($user->email, 'identity_verification_link', [
            '{username}'   => $user->name,
            '{verify_url}' => $verifyUrl,
            '{site_name}'  => AppModelsSetting::getValue('site_name', config('app.name')),
        ]);

        return response()->json(['message' => 'Verification link sent.']);
    }

    // Public: validate token and return the user's first name for the form page
    public function showForm(string $token): JsonResponse
    {
        $v = IdentityVerification::with('user:id,name')->where('token', $token)->first();

        if (!$v) {
            return response()->json(['message' => 'Invalid verification link.'], 404);
        }

        if ($v->status === 'submitted') {
            return response()->json(['message' => 'You have already submitted your ID. Our team will review it shortly.'], 409);
        }

        if ($v->expires_at && $v->expires_at->isPast()) {
            return response()->json(['message' => 'This verification link has expired. Please contact support to request a new one.'], 410);
        }

        return response()->json([
            'valid'      => true,
            'name'       => $v->user->name ?? 'there',
            'expires_at' => $v->expires_at,
        ]);
    }

    // Public: accept the ID proof upload
    public function submit(Request $request, string $token): JsonResponse
    {
        $v = IdentityVerification::where('token', $token)->first();

        if (!$v) {
            return response()->json(['message' => 'Invalid verification link.'], 404);
        }

        if ($v->status === 'submitted') {
            return response()->json(['message' => 'Already submitted.'], 409);
        }

        if ($v->expires_at && $v->expires_at->isPast()) {
            return response()->json(['message' => 'This link has expired.'], 410);
        }

        $request->validate([
            'id_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $path = $request->file('id_proof')->store('identity-proofs/' . $v->user_id);

        $v->update([
            'id_proof_url' => $path,
            'status'       => 'submitted',
            'used_at'      => now(),
        ]);

        return response()->json(['message' => 'Your ID has been submitted successfully. Our team will review it and you will receive an email once your account is reactivated.']);
    }
}
