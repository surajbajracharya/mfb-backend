<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyInitializerService;
use App\Services\FrontendScaffolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = Company::withCount(['users', 'courses'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($companies);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount(['users', 'courses']);
        return response()->json($company);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'slug'    => ['required', 'string', 'max:100', 'unique:companies,slug', 'regex:/^[a-z0-9\-]+$/'],
            'domain'  => ['required', 'string', 'unique:companies,domain'],
            'api_url' => ['nullable', 'string', 'url', 'max:500'],
            'logo'    => ['nullable', 'string', 'url'],
        ]);

        $company = Company::create($data);

        cache()->forget('cors_allowed_origins');

        app(CompanyInitializerService::class)->initialize($company);

        $frontendPath = null;
        $deployHint   = null;
        try {
            $frontendPath = app(FrontendScaffolderService::class)->scaffold($company);
            $deployHint   = "cd \"{$frontendPath}\" && npm install && npm run build";
        } catch (\Throwable $e) {
            $frontendPath = null;
            $deployHint   = 'Frontend scaffolding failed: ' . $e->getMessage();
        }

        return response()->json([
            'company'       => $company,
            'frontend_path' => $frontendPath,
            'deploy_hint'   => $deployHint,
        ], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'slug'            => ['sometimes', 'string', 'max:100', 'unique:companies,slug,' . $company->id],
            'domain'          => ['sometimes', 'string', 'unique:companies,domain,' . $company->id],
            'api_url'         => ['nullable', 'string', 'url', 'max:500'],
            'logo'            => ['nullable', 'string', 'url'],
            'is_active'       => ['sometimes', 'boolean'],
            'plan_type'       => ['sometimes', 'string', 'in:standard,premium,enterprise'],
            'modules_enabled' => ['sometimes', 'array'],
            'modules_enabled.*' => ['string', 'in:courses,events,appointments,resources,plans'],
        ]);

        $oldDomain = $company->domain;
        $company->update($data);

        cache()->forget('cors_allowed_origins');
        // Clear tenant domain cache for old and new domain
        cache()->forget('tenant_domain_' . md5($oldDomain));
        if (isset($data['domain']) && $data['domain'] !== $oldDomain) {
            cache()->forget('tenant_domain_' . md5($data['domain']));
        }

        return response()->json($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        // Soft deactivate — never hard delete (preserves user data)
        $company->update(['is_active' => false]);

        cache()->forget('cors_allowed_origins');

        return response()->json(['message' => 'Company deactivated successfully.']);
    }

    public function reactivate(Company $company): JsonResponse
    {
        $company->update(['is_active' => true]);

        cache()->forget('cors_allowed_origins');

        return response()->json(['message' => 'Company reactivated successfully.']);
    }
}
