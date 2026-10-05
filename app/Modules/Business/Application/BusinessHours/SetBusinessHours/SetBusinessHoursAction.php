<?php

namespace App\Modules\Business\Application\BusinessHours\SetBusinessHours;

use App\Core\Services\AuditService;
use App\Modules\Business\Repositories\BusinessHourRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;

class SetBusinessHoursAction
{
    public function __construct(
        private readonly BusinessHourRepository $hourRepository,
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly AuditService $auditService
    ) {}

    public function execute(SetBusinessHoursInput $input): SetBusinessHoursResult
    {
        $this->hourRepository->syncHours($input->tenantId, $input->hours);

        $updatedHours = $this->hourRepository->getByTenantId($input->tenantId);

        // Recompilar snapshot de la base de conocimiento en Redis para la IA
        $this->knowledgeCompiler->compile($input->tenantId);

        // Auditoría
        $this->auditService->record('Business', 'BusinessHoursUpdated', newValues: ['count' => count($input->hours)]);

        return new SetBusinessHoursResult($updatedHours);
    }
}
