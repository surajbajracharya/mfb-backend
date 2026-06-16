<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // auth:sanctum runs as route middleware (before group middleware returns),
        // so $request->user() is populated by the time we reach here.
        if ($user = $request->user()) {
            $lastSeen = $user->getRawOriginal('last_seen_at');
            if (!$lastSeen || now()->diffInSeconds(\Carbon\Carbon::parse($lastSeen)) > 60) {
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
            }
        }

        return $response;
    }
}
