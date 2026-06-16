<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\CertificateController;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AdminCertificateController extends Controller
{
    public function index(): JsonResponse
    {
        $certificates = Certificate::with(['user:id,name,email', 'course:id,title'])
            ->orderByDesc('issued_at')
            ->paginate(25);

        return response()->json($certificates);
    }

    public function download(string $id): Response
    {
        $cert = Certificate::with('user', 'course.instructor')->findOrFail($id);
        $filename = 'certificate-' . $cert->certificate_number . '.pdf';

        // Serve the cached PDF the customer already generated — don't regenerate
        if ($cert->pdf_path && Storage::disk('local')->exists($cert->pdf_path)) {
            return response(Storage::disk('local')->get($cert->pdf_path), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // No cached PDF yet — generate once and cache it
        return CertificateController::generatePdf($cert);
    }

    public function getTemplate(): JsonResponse
    {
        return response()->json(CertificateTemplate::getDefault());
    }

    public function updateTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logo_left'               => ['nullable', 'string'],
            'logo_right'              => ['nullable', 'string'],
            'background_color'        => ['sometimes', 'string', 'max:20'],
            'border_color'            => ['sometimes', 'string', 'max:20'],
            'border_width'            => ['sometimes', 'integer', 'min:0', 'max:30'],
            'title'                   => ['sometimes', 'string', 'max:255'],
            'title_color'             => ['sometimes', 'string', 'max:20'],
            'body_html'               => ['sometimes', 'string'],
            'footer_text'             => ['nullable', 'string'],
            'signature_image'         => ['nullable', 'string'],
            'signature_label'         => ['sometimes', 'string', 'max:255'],
            'show_certificate_number' => ['sometimes', 'boolean'],
            'show_date'               => ['sometimes', 'boolean'],
        ]);

        $tpl = CertificateTemplate::getDefault();
        $tpl->update($data);

        return response()->json($tpl);
    }
}
