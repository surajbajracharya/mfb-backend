<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\Company;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class CompanyInitializerService
{
    /**
     * Seed all required data for a newly created company.
     * Runs entirely outside the tenant scope — uses explicit company_id on every insert.
     */
    public function initialize(Company $company): void
    {
        DB::transaction(function () use ($company) {

            // 1. Clone global email templates (rows with company_id = NULL)
            $globalTemplates = EmailTemplate::withoutCompanyScope()
                ->whereNull('company_id')
                ->get();
            foreach ($globalTemplates as $tpl) {
                EmailTemplate::withoutCompanyScope()->create(array_merge(
                    $tpl->only(['key', 'name', 'subject', 'body_html', 'available_shortcodes', 'is_active']),
                    ['company_id' => $company->id]
                ));
            }

            // 3. Clone global email settings (row with company_id = NULL)
            $globalSettings = EmailSetting::withoutCompanyScope()
                ->whereNull('company_id')
                ->first();
            if ($globalSettings) {
                EmailSetting::withoutCompanyScope()->create(array_merge(
                    $globalSettings->only([
                        'header_bg_color', 'header_text_color', 'header_tagline',
                        'footer_html', 'footer_bg_color', 'footer_text_color', 'social_links',
                    ]),
                    [
                        'company_id'  => $company->id,
                        'site_name'   => $company->name,
                        'from_name'   => $company->name,
                        'from_email'  => 'noreply@' . $company->domain,
                        'header_logo' => $company->logo,
                        'footer_html' => '<p>© ' . date('Y') . ' ' . $company->name . '. All rights reserved.</p>',
                    ]
                ));
            }

            // 4. Create default certificate template for this company
            CertificateTemplate::withoutCompanyScope()->create([
                'company_id'               => $company->id,
                'body_html'                => $this->defaultCertBody(),
                'footer_text'              => $company->name . ' · LMS Platform',
                'title'                    => 'Certificate of Completion',
                'background_color'         => '#ffffff',
                'border_color'             => '#6366f1',
                'border_width'             => 6,
                'title_color'              => '#4f46e5',
                'signature_label'          => 'Authorized Signature',
                'show_certificate_number'  => true,
                'show_date'                => true,
            ]);

            // 5. Seed minimal settings
            foreach (['site_name' => $company->name, 'company_name' => $company->name] as $key => $value) {
                Setting::withoutCompanyScope()->create([
                    'company_id' => $company->id,
                    'key'        => $key,
                    'value'      => $value,
                ]);
            }

        });
    }

    private function defaultCertBody(): string
    {
        return '<p style="text-align:center;font-size:16px;color:#555555;">This is to certify that</p>'
             . '<p style="text-align:center;font-size:36px;font-weight:bold;color:#1f2937;font-style:italic;margin:12px 0;">{{student_name}}</p>'
             . '<p style="text-align:center;font-size:16px;color:#555555;margin-bottom:8px;">has successfully completed the course</p>'
             . '<p style="text-align:center;font-size:24px;font-weight:bold;color:#4f46e5;margin-bottom:16px;">{{course_title}}</p>'
             . '<p style="text-align:center;font-size:14px;color:#777777;">with flying colors and dedication.</p>';
    }
}
