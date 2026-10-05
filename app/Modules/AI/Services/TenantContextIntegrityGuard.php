<?php

namespace App\Modules\AI\Services;

use App\Core\DTOs\TenantContextDTO;
use App\Core\Exceptions\TenantContextMismatchException;

/**
 * Centinela Constitucional de Integridad Multi-Tenant (TCI-07).
 * Valida que los 7 factores del flujo comercial compartan exactamente el mismo tenant_id.
 */
class TenantContextIntegrityGuard
{
    /**
     * Valida la igualdad estricta de las 7 capas del contexto contra el TenantContextDTO.
     *
     * @param TenantContextDTO $context
     * @param array $factors [
     *    'gateway_binding' => int,
     *    'inbound_message' => int,
     *    'conversation'    => int|object,
     *    'customer'        => int|object,
     *    'business'        => int|object,
     *    'catalog'         => int|object,
     *    'ai_context'      => int
     * ]
     *
     * @throws TenantContextMismatchException
     */
    public static function assertIntegrity(TenantContextDTO $context, array $factors): void
    {
        $expectedTenantId = $context->tenantId;

        foreach ($factors as $factorName => $entityOrId) {
            if ($entityOrId === null) {
                continue;
            }

            $entityTenantId = null;
            if (is_numeric($entityOrId)) {
                $entityTenantId = (int) $entityOrId;
            } elseif (is_object($entityOrId)) {
                // Ajuste 1: business_id != tenant_id; extraer siempre el atributo tenant_id
                $entityTenantId = isset($entityOrId->tenant_id) ? (int) $entityOrId->tenant_id : null;
            } elseif (is_array($entityOrId)) {
                $entityTenantId = isset($entityOrId['tenant_id']) ? (int) $entityOrId['tenant_id'] : null;
            }

            if ($entityTenantId !== null && $entityTenantId !== $expectedTenantId) {
                throw new TenantContextMismatchException(
                    "Violación de Integridad Multi-Tenant (TCI-07): Se detectó contaminación en capa [{$factorName}]. Se esperaba Tenant [{$expectedTenantId}], pero se encontró [{$entityTenantId}].",
                    $context->correlationId
                );
            }
        }
    }
}
