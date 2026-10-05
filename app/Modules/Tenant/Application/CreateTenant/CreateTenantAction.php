<?php

namespace App\Modules\Tenant\Application\CreateTenant;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Contracts\Repositories\TenantRepositoryInterface;
use App\Core\Enums\TenantStatus;
use App\Core\Events\TenantCreated;
use App\Core\Services\AuditService;

class CreateTenantAction
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(CreateTenantInput $input): CreateTenantResult
    {
        $tenant = $this->tenantRepository->create([
            'name' => $input->name,
            'slug' => strtolower($input->slug),
            'business_type' => $input->businessType,
            'country' => $input->country,
            'currency' => $input->currency,
            'timezone' => $input->timezone,
            'status' => TenantStatus::ACTIVE->value,
        ]);

        // Registrar auditoría
        $this->auditService->record(
            module: 'Tenant',
            action: 'TenantCreated',
            newValues: ['uuid' => $tenant->uuid, 'name' => $tenant->name, 'slug' => $tenant->slug]
        );

        // Despachar Domain Event
        $this->eventBus->dispatch(new TenantCreated($tenant));

        return new CreateTenantResult($tenant);
    }
}
