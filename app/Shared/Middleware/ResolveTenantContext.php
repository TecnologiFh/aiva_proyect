<?php

namespace App\Shared\Middleware;

use App\Core\Contracts\Repositories\TenantRepositoryInterface;
use App\Core\Exceptions\TenantNotFoundException;
use App\Core\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $tenant = null;

        // 1. Resolver por usuario autenticado (Token Sanctum)
        if (Auth::check() && Auth::user()->tenant_id) {
            $tenant = $this->tenantRepository->findById(Auth::user()->tenant_id);
        }

        // 2. Resolver por Subdominio / Dominio HTTP (si no se resolvió por Token)
        if (!$tenant) {
            $host = $request->getHost(); // ej. donutsfs.aiva.pe o donutsfs.com
            $subdomain = explode('.', $host)[0];
            
            if ($subdomain && !in_array($subdomain, ['www', 'api', 'localhost', 'aiva'])) {
                $tenant = $this->tenantRepository->findBySlug($subdomain);
            }
        }

        // 3. Resolver por Cabecera HTTP (X-Tenant-Slug)
        if (!$tenant && $request->hasHeader('X-Tenant-Slug')) {
            $tenant = $this->tenantRepository->findBySlug($request->header('X-Tenant-Slug'));
        }

        // 4. Resolver por Cabecera HTTP (X-Tenant-ID / UUID)
        if (!$tenant && $request->hasHeader('X-Tenant-ID')) {
            $headerId = $request->header('X-Tenant-ID');
            $tenant = is_numeric($headerId)
                ? $this->tenantRepository->findById((int) $headerId)
                : $this->tenantRepository->findByUuid($headerId);
        }

        if ($tenant) {
            TenantContext::setTenant($tenant);
        }

        return $next($request);
    }
}
