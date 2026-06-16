<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Services\TenantContext;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TenantContext is a per-request singleton (reset on each request cycle)
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Sanctum must resolve users without the company global scope so that
        // global admins (company_id = null) can authenticate via any company frontend.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Use extension-based MIME detection so uploads work on servers without the
        // PHP fileinfo extension (e.g. ea-php83 on cPanel without fileinfo.so installed).
        Storage::extend('local-ext', function ($app, $config) {
            $adapter = new LocalFilesystemAdapter(
                $config['root'],
                null,
                LOCK_EX,
                LocalFilesystemAdapter::DISALLOW_LINKS,
                new ExtensionMimeTypeDetector(),
            );
            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });

        // Super admin bypasses all permission checks — they can do everything.
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // ── Dynamic CORS ──────────────────────────────────────────────────────
        try {
            $origins = cache()->remember('cors_allowed_origins', 300, function () {
                // Admin + frontend origins from .env (reliable fallback even if company domain not set in DB)
                $base = array_filter([env('ADMIN_URL'), env('FRONTEND_URL')]);

                // Every active company's frontend domain is the source of truth — no hardcoding needed
                $companies = \App\Models\Company::withoutGlobalScope('company')
                    ->where('is_active', true)
                    ->pluck('domain');
                foreach ($companies as $domain) {
                    $base[] = 'https://' . $domain;
                    $base[] = 'http://'  . $domain;
                }

                return array_values(array_unique($base));
            });
            config(['cors.allowed_origins' => $origins]);
        } catch (\Throwable) {
            // DB not ready (fresh install / migrations) — use defaults from cors.php
        }

    }
}
