<?php

namespace App\Core\Services;

use App\Core\DTOs\TenantContextDTO;
use App\Modules\Tenant\Models\Tenant;

class TenantContext
{
    private static ?int $tenantId = null;
    private static ?Tenant $tenant = null;
    private static ?TenantContextDTO $context = null;

    public static function setTenant(Tenant $tenant): void
    {
        self::$tenantId = $tenant->id;
        self::$tenant = $tenant;
    }

    public static function setTenantId(int $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function setContext(TenantContextDTO $context): void
    {
        self::$context = $context;
        self::$tenantId = $context->tenantId;
    }

    public static function getContext(): ?TenantContextDTO
    {
        return self::$context;
    }

    public static function getTenantId(): ?int
    {
        return self::$tenantId ?? self::$context?->tenantId;
    }

    public static function getTenant(): ?Tenant
    {
        return self::$tenant;
    }

    public static function hasTenant(): bool
    {
        return (self::$tenantId !== null) || (self::$context !== null);
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$tenant = null;
        self::$context = null;
    }
}
