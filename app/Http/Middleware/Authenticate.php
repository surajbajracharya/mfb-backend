<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * This is an API-only backend — never redirect to a login page.
     * Returning null causes the parent to throw AuthenticationException,
     * which bootstrap/app.php converts to a 401 JSON response.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
