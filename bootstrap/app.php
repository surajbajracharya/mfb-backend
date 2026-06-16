<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        $middleware->appendToGroup('api', \App\Http\Middleware\UpdateLastSeen::class);

        $middleware->alias([
            // API-only Authenticate: never redirects to login, returns null → clean 401 JSON
            'auth'               => \App\Http\Middleware\Authenticate::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'maintenance'        => \App\Http\Middleware\CheckMaintenanceMode::class,
            'tenant'             => \App\Http\Middleware\ResolveTenant::class,
            'super_switch'       => \App\Http\Middleware\ApplySuperAdminSwitch::class,
            'company_timezone'   => \App\Http\Middleware\SetCompanyTimezone::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => $e->errors(),
                    ], 422);
                }
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json(['message' => 'Resource not found.'], 404);
                }
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }
            }
        });
    })->create();
