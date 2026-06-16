<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class EmailSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'site_name', 'from_name', 'from_email',
        'header_logo', 'header_bg_color', 'header_text_color', 'header_tagline',
        'footer_html', 'footer_bg_color', 'footer_text_color', 'social_links',
        'company_id',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public static function getSettings(): self
    {
        $settings = static::first();
        if (!$settings) {
            $siteName = \App\Models\Setting::getValue('site_name', config('app.name'));
            $settings = static::create([
                'site_name'         => $siteName,
                'from_name'         => $siteName,
                'from_email'        => \App\Models\Setting::getValue('mail_from_address', config('mail.from.address')),
                'header_bg_color'   => '#6366f1',
                'header_text_color' => '#ffffff',
            ]);
        }
        return $settings;
    }
}
