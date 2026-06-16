<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $companyId = app(\App\Services\TenantContext::class)->companyId() ?? 'global';
            $cacheKey  = 'maintenance_mode_' . $companyId;
            $isOn = cache()->remember($cacheKey, 60, fn () =>
                \App\Models\Setting::getValue('maintenance_mode', '0')
            );

            if ($isOn === '1') {
                return response()->json([
                    'message'     => 'Site is currently under maintenance.',
                    'maintenance' => true,
                ], 503);
            }
        } catch (\Exception) {
            // DB unavailable — allow through
        }

        return $next($request);
    }
}
