<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantStatusCheckMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = session('active_tenant_id') ?? $request->input('tenant_id') ?? $request->header('X-Tenant-ID');

        if ($tenantId) {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();

            if ($tenant && in_array(strtoupper($tenant->status), ['SUSPENDED', 'ARCHIVED'])) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => true,
                        'status' => 403,
                        'message' => "Acceso denegado: El Tenant #{$tenantId} ({$tenant->name}) se encuentra suspendido."
                    ], 403);
                }

                throw new HttpException(403, "Acceso denegado: El Tenant #{$tenantId} ({$tenant->name}) se encuentra suspendido por la administración de AIVA.");
            }
        }

        return $next($request);
    }
}
