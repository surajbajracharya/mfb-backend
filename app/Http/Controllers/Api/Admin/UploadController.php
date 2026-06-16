<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private function companyPrefix(): string
    {
        $companyId = app(TenantContext::class)->companyId();
        return $companyId ? 'company_' . $companyId . '/' : '';
    }

    private function authorizeDeletePath(string $path): bool
    {
        if (!str_starts_with($path, 'uploads/')) {
            return false;
        }

        $ctx       = app(TenantContext::class);
        $companyId = $ctx->companyId();

        // Super admin in global mode (no company selected) can delete any file
        if ($ctx->isGlobal() || !$companyId) {
            return true;
        }

        return str_starts_with($path, 'uploads/company_' . $companyId . '/');
    }

    public function uploadPdf(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $file     = $request->file('file');
        $filename = Str::uuid() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.pdf';
        $path     = $file->storeAs('uploads/' . $this->companyPrefix() . 'pdfs', $filename, 'public');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'url'  => asset('storage/' . $path),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
    }

    public function uploadAudio(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:mp3,wav,ogg,aac,m4a,flac', 'max:102400'],
        ]);

        $file     = $request->file('file');
        $ext      = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $ext;
        $path     = $file->storeAs('uploads/' . $this->companyPrefix() . 'audio', $filename, 'public');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'url'  => asset('storage/' . $path),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
    }

    public function deleteAudio(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string']]);

        $path = $request->input('path');

        if (!$this->authorizeDeletePath($path)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        \Storage::disk('public')->delete($path);

        return response()->json(['message' => 'Deleted.']);
    }

    public function deletePdf(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string']]);

        $path = $request->input('path');

        if (!$this->authorizeDeletePath($path)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        \Storage::disk('public')->delete($path);

        return response()->json(['message' => 'Deleted.']);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,svg,webp', 'max:5120'],
        ]);

        $file     = $request->file('file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('uploads/' . $this->companyPrefix() . 'images', $filename, 'public');

        return response()->json([
            'url'  => asset('storage/' . $path),
            'path' => $path,
        ]);
    }

    public function deleteImage(Request $request): JsonResponse
    {
        $request->validate(['path' => ['required', 'string']]);

        $path = $request->input('path');

        if (!$this->authorizeDeletePath($path)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        \Storage::disk('public')->delete($path);

        return response()->json(['message' => 'Deleted.']);
    }
}
