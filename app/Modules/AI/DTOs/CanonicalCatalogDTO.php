<?php

namespace App\Modules\AI\DTOs;

final class CanonicalCatalogDTO
{
    /**
     * @param MenuGroupDTO[] $menuGroups
     * @param array $unassociatedProducts
     */
    public function __construct(
        public readonly int $version,
        public readonly int $tenantId,
        public readonly string $status = 'PUBLISHED', // DRAFT, PUBLISHED, SUPERSEDED (RF-06, RF-31)
        public readonly array $menuGroups = [],
        public readonly array $unassociatedProducts = [],
        public readonly ?string $generatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'tenant_id' => $this->tenantId,
            'status' => $this->status,
            'menu_groups' => array_map(fn(MenuGroupDTO $mg) => $mg->toArray(), $this->menuGroups),
            'unassociated_products' => $this->unassociatedProducts,
            'generated_at' => $this->generatedAt ?? now()->toIso8601String(),
        ];
    }
}
