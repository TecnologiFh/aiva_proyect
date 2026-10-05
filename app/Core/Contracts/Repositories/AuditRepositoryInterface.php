<?php

namespace App\Core\Contracts\Repositories;

use App\Modules\Identity\Models\AuditLog;

interface AuditRepositoryInterface
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
    ): AuditLog;
}
