<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'api_url',
        'logo',
        'is_active',
        'plan_type',
        'modules_enabled',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'modules_enabled' => 'array',
    ];

    public function isModuleEnabled(string $module): bool
    {
        $modules = $this->modules_enabled ?? ['courses', 'events', 'appointments', 'resources'];
        return in_array($module, $modules, true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function emailSettings(): HasOne
    {
        return $this->hasOne(EmailSetting::class);
    }

    public function certificateTemplate(): HasOne
    {
        return $this->hasOne(CertificateTemplate::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve a company by any of its three domain fields.
     * Called in ResolveTenant middleware on every request.
     */
    public static function resolveFromDomain(string $domain): ?self
    {
        return static::where('is_active', true)
            ->where('domain', $domain)
            ->first();
    }
}
