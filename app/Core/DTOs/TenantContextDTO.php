<?php

namespace App\Core\DTOs;

/**
 * DTO Inmutable Request-Scoped que encapsula la identidad y correlación Multi-Tenant (TCI-03-A).
 */
final readonly class TenantContextDTO
{
    public function __construct(
        public int $tenantId,
        public int $businessId,
        public ?int $catalogVersionId = null,
        public string $correlationId = ''
    ) {}

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'business_id' => $this->businessId,
            'catalog_version_id' => $this->catalogVersionId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
