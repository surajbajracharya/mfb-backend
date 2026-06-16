<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class EmailTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'key', 'name', 'subject', 'body_html', 'available_shortcodes', 'is_active', 'company_id',
    ];

    protected $casts = [
        'available_shortcodes' => 'array',
        'is_active'            => 'boolean',
    ];

    public static function findByKey(string $key): ?self
    {
        // Try company-specific template first (scoped to current company)
        $template = static::where('key', $key)->where('is_active', true)->first();
        if ($template) return $template;

        // Fall back to global template (company_id = NULL) — seeded by migrations
        return static::withoutGlobalScope('company')
            ->where('key', $key)
            ->where('is_active', true)
            ->whereNull('company_id')
            ->first();
    }
}
