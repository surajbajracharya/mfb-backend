<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->with('course:id,title,slug,thumbnail')
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => $certificates]);
    }

    public function download(Request $request, string $number): Response
    {
        $cert = Certificate::where('certificate_number', $number)
            ->where('user_id', $request->user()->id)
            ->with('user', 'course.instructor')
            ->firstOrFail();

        $filename = 'certificate-' . $cert->certificate_number . '.pdf';

        // Serve the cached PDF without regenerating
        if ($cert->pdf_path && Storage::disk('local')->exists($cert->pdf_path)) {
            if (!$cert->first_downloaded_at) {
                $cert->updateQuietly(['first_downloaded_at' => now()]);
            }
            return response(Storage::disk('local')->get($cert->pdf_path), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // Generate, store, and record first download time
        $bytes = $this->buildPdfBytes($cert);
        $path  = 'certificates/' . $cert->certificate_number . '.pdf';
        Storage::disk('local')->put($path, $bytes);
        $cert->update([
            'pdf_path'            => $path,
            'first_downloaded_at' => now(),
        ]);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildPdfBytes(Certificate $cert): string
    {
        // Company-specific template first, then global (null company_id), then any
        $tpl = CertificateTemplate::withoutCompanyScope()
            ->where('company_id', $cert->company_id)
            ->first()
            ?? CertificateTemplate::withoutCompanyScope()->whereNull('company_id')->first()
            ?? CertificateTemplate::withoutCompanyScope()->first()
            ?? CertificateTemplate::getDefault();

        // Use company logo when template has none set
        $company   = $cert->company_id ? \App\Models\Company::find($cert->company_id) : null;
        $logoLeft  = $tpl->logo_left  ?: ($company?->logo ?? null);
        $logoRight = $tpl->logo_right ?: null;

        $placeholders = [
            '{{student_name}}'       => $cert->user->name ?? 'Student',
            '{{course_title}}'       => $cert->course->title ?? 'Course',
            '{{instructor_name}}'    => $cert->course->instructor->name ?? '',
            '{{issued_date}}'        => $cert->issued_at->format('F j, Y'),
            '{{certificate_number}}' => $cert->certificate_number,
        ];

        $body = str_replace(array_keys($placeholders), array_values($placeholders), $tpl->body_html);

        return Pdf::loadView('certificates.pdf', [
            'tpl'               => $tpl,
            'body'              => $body,
            'logoLeft'          => $logoLeft,
            'logoRight'         => $logoRight,
            'companyName'       => $company?->name ?? config('app.name'),
            'issuedDate'        => $cert->issued_at->format('F j, Y'),
            'certificateNumber' => $cert->certificate_number,
        ])->setPaper('a4', 'landscape')->output();
    }

    /**
     * Admin download — regenerates fresh (used by admin panel).
     */
    public static function generatePdf(Certificate $cert): Response
    {
        $controller = new self();
        $bytes      = $controller->buildPdfBytes($cert);
        $filename   = 'certificate-' . $cert->certificate_number . '.pdf';
        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
