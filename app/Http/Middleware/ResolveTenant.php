<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        // 1. Match against the frontend Origin header (e.g. "http://localhost:3000")
        //    Admins store the frontend domain (with port) in companies.domain so that
        //    public API calls from that frontend are automatically scoped to the right company.
        $origin = $request->header('Origin');
        if ($origin) {
            $parsed     = parse_url($origin);
            $originHost = $parsed['host'] ?? null;
            if ($originHost) {
                // Try "host:port" first (e.g. "localhost:3000"), then bare "host"
                $withPort = isset($parsed['port']) ? "{$originHost}:{$parsed['port']}" : $originHost;
                $company  = $this->resolve($withPort) ?? $this->resolve($originHost);
                if ($company) {
                    $context->setCompany($company);
                    return $next($request);
                }
            }
        }

        // 2. Explicit X-Company-ID header — sent by all frontend api.ts clients.
        //    This is the authoritative fallback when Origin resolution fails
        //    (e.g. same-origin requests, Postman, mobile apps, or domains not yet in DB).
        $companyId = $request->header('X-Company-ID');
        if ($companyId && is_numeric($companyId)) {
            $company = cache()->remember(
                'tenant_id_' . (int) $companyId,
                120,
                fn () => \App\Models\Company::find((int) $companyId)
            );
            if ($company) {
                $context->setCompany($company);
                return $next($request);
            }
        }

        // 3. Fall back to the API server's own domain (direct API access, same-domain setups)
        $context->setCompany($this->resolve($request->getHost()));

        return $next($request);
    }

    /** Resolve company by domain with a short cache to avoid a DB hit on every request. */
    private function resolve(string $domain): ?Company
    {
        return cache()->remember(
            'tenant_domain_' . md5($domain),
            120, // 2 minutes — short enough to pick up domain changes quickly
            fn () => Company::resolveFromDomain($domain)
        );
    }
}
