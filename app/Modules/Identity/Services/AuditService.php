<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Registrar evento de auditoría de forma estrictamente INMUTABLE (solo INSERT).
     */
    public static function logAction(
        string $action,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?int $tenantId = null,
        ?string $resource = null,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $correlationId = request()->header('X-Correlation-ID')
            ?? (app()->bound('correlation_id') ? app('correlation_id') : (string) Str::uuid());

        try {
            AuditLog::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId ?? session('active_tenant_id'),
                'user_id' => $actorUserId ?? auth()->id(),
                'correlation_id' => $correlationId,
                'module' => 'IDENTITY_ADMIN',
                'action' => strtoupper($action),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? 'System', 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
