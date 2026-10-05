<?php

use App\Providers\ModuleDiscoveryServiceProvider;
use App\Shared\Middleware\CorrelationIdMiddleware;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhook',
            'whatsapp/webhook',
            'api/v1/messaging/*',
            'api/v1/dashboard/*',
            'ai-playground/*',
        ]);

        $middleware->web(prepend: [
            CorrelationIdMiddleware::class,
        ]);

        $middleware->api(prepend: [
            CorrelationIdMiddleware::class,
        ]);

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
        ]);
    })
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        ModuleDiscoveryServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response, \Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : ($response->getStatusCode() ?: 500);
                $isProduction = !config('app.debug', false);

                return response()->json([
                    'error' => true,
                    'status' => $statusCode >= 500 ? 'INTERNAL_ERROR' : 'REQUEST_ERROR',
                    'message' => ($isProduction && $statusCode >= 500) ? 'Ocurrió un error interno en el servidor.' : $e->getMessage(),
                    'correlation_id' => $request->header('X-Correlation-ID', app()->bound('correlation_id') ? app('correlation_id') : null),
                ], $statusCode);
            }
            return $response;
        });
    })->create();
