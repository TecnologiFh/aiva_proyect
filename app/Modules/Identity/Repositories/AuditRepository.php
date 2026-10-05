<?php

namespace App\Modules\Identity\Repositories;

use App\Core\Contracts\Repositories\AuditRepositoryInterface;
use App\Modules\Identity\Models\AuditLog;

class AuditRepository implements AuditRepositoryInterface
{
    public function log(
        string $module,
        string $action,
        ?int $tenantId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $correlationId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'correlation_id' => $correlationId,
            'module' => $module,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
