<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class CertificateTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'logo_left', 'logo_right',
        'background_color', 'border_color', 'border_width',
        'title', 'title_color',
        'body_html',
        'footer_text',
        'signature_image', 'signature_label',
        'show_certificate_number', 'show_date',
        'company_id',
    ];

    protected $casts = [
        'show_certificate_number' => 'boolean',
        'show_date'               => 'boolean',
        'border_width'            => 'integer',
    ];

    /** Always return (and create) the single global template. */
    public static function getDefault(): self
    {
        return self::first() ?? self::create([
            'body_html'   => '<p style="text-align:center;font-size:18px;color:#555555;">This is to certify that</p><p style="text-align:center;font-size:42px;font-weight:bold;color:#1f2937;font-style:italic;margin:14px 0;">{{student_name}}</p><p style="text-align:center;font-size:18px;color:#555555;margin-bottom:10px;">has successfully completed the course</p><p style="text-align:center;font-size:28px;font-weight:bold;color:#4f46e5;margin-bottom:18px;">{{course_title}}</p><p style="text-align:center;font-size:16px;color:#777777;">with flying colors and dedication.</p>',
            'footer_text' => (\App\Models\Setting::getValue('site_name') ?? config('app.name')) . ' · Holistic Meditation & Wellness',
        ]);
    }
}
