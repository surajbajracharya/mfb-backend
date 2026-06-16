<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySuperAdminSwitch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user    = $request->user();
        $context = app(TenantContext::class);

        if ($user && $user->hasRole('super_admin')) {
            $context->setIsSuperAdmin(true);

            $companyId = $request->header('X-Company-ID');
            if ($companyId) {
                $company = Company::find((int) $companyId);
                $context->setCompany($company ?: null);
            } else {
                // Super admin with no company selected → global view (no scope)
                $context->setCompany(null);
            }
        } elseif ($user && $user->company_id) {
            // Regular company user — always scope to their own company
            $context->setCompany(Company::find($user->company_id));
        }

        return $next($request);
    }
}
