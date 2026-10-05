<?php

namespace App\Core\Services;

use App\Core\Contracts\Repositories\AuditRepositoryInterface;
use App\Core\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function __construct(
        private readonly AuditRepositoryInterface $auditRepository
    ) {}

    public function record(
        string $module,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $correlationId = Request::header('X-Correlation-ID');
        $tenantId = TenantContext::getTenantId();
        $userId = Auth::id();
        $ip = Request::ip();
        $userAgent = Request::userAgent();

        $this->auditRepository->log(
            module: $module,
            action: $action,
            tenantId: $tenantId,
            userId: $userId,
            oldValues: $oldValues,
            newValues: $newValues,
            correlationId: $correlationId,
            ipAddress: $ip,
            userAgent: $userAgent
        );
    }
}
