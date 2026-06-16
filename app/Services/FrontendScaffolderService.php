<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\File;

class FrontendScaffolderService
{
    /** Directories/files to skip when copying the frontend template */
    private array $exclude = [
        'node_modules',
        '.next',
        '.env.local',
        '.env',
        '.git',
    ];

    /**
     * Scaffold a new frontend folder for the given company.
     * Source: {project_root}/frontend/
     * Destination: {project_root}/companies/{slug}/frontend/
     *
     * @return string The absolute path to the scaffolded frontend folder
     */
    public function scaffold(Company $company): string
    {
        $root   = rtrim(base_path('..'), DIRECTORY_SEPARATOR);
        $source = $root . DIRECTORY_SEPARATOR . 'frontend';
        $dest   = $root . DIRECTORY_SEPARATOR . 'companies'
                . DIRECTORY_SEPARATOR . $company->slug
                . DIRECTORY_SEPARATOR . 'frontend';

        if (!File::isDirectory($source)) {
            throw new \RuntimeException("Frontend template not found at: {$source}");
        }

        File::makeDirectory($dest, 0755, true, true);

        $this->copyDirectory($source, $dest);

        // Write company-specific .env.local
        // NEXT_PUBLIC_API_URL → the ONE shared backend API for all companies.
        // Theme vars (PRIMARY_COLOR etc.) are baked into the JS bundle at build time,
        // so each company's deployed frontend has its own branding without runtime DB calls.
        $sharedApiUrl = rtrim(env('SCAFFOLD_API_URL', env('APP_URL', 'http://localhost:8002')), '/') . '/api/v1';
        $env = implode("\n", [
            '# ── API (shared across all companies) ────────────────────────────────',
            'NEXT_PUBLIC_API_URL=' . $sharedApiUrl,
            '',
            '# ── Company identity ──────────────────────────────────────────────────',
            'NEXT_PUBLIC_SITE_NAME=' . $company->name,
            'NEXT_PUBLIC_TAGLINE=Your Wellness Platform',
            'NEXT_PUBLIC_COMPANY_DOMAIN=' . $company->domain,
            'NEXT_PUBLIC_COMPANY_ID=' . $company->id,
            'NEXT_PUBLIC_LOGO_URL=' . ($company->logo ?? ''),
            '',
            '# ── Theme / Branding (customize per company) ─────────────────────────',
            '# Override any of these to change the company frontend\'s look & feel.',
            '# Run `npm run build` after editing to apply changes.',
            'NEXT_PUBLIC_PRIMARY_COLOR=#6366f1',
            'NEXT_PUBLIC_SECONDARY_COLOR=#8b5cf6',
            'NEXT_PUBLIC_ACCENT_COLOR=#06b6d4',
            'NEXT_PUBLIC_FONT_FAMILY=Inter',
            '',
        ]);
        File::put($dest . DIRECTORY_SEPARATOR . '.env.local', $env);

        return $dest;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach (File::allFiles($src, true) as $file) {
            $relativePath = $file->getRelativePathname();

            // Skip excluded top-level directory/file names
            $parts    = explode(DIRECTORY_SEPARATOR, $relativePath);
            $topLevel = $parts[0];
            if (in_array($topLevel, $this->exclude, true)) {
                continue;
            }
            if (in_array($file->getFilename(), $this->exclude, true)) {
                continue;
            }

            $destPath = $dst . DIRECTORY_SEPARATOR . $relativePath;
            File::makeDirectory(dirname($destPath), 0755, true, true);
            File::copy($file->getPathname(), $destPath);
        }
    }
}
