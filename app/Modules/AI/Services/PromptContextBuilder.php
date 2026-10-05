<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\DTOs\PromptContextDTO;
use App\Modules\AI\Repositories\CustomerMemoryRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;

class PromptContextBuilder
{
    public function __construct(
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly CustomerMemoryRepository $memoryRepository
    ) {}

    public function build(int $tenantId, ?string $customerPhone = null, array $recentMessages = []): PromptContextDTO
    {
        // 1. Obtener el snapshot compilado desde Redis (0ms latencia)
        $knowledge = $this->knowledgeCompiler->getSnapshot($tenantId) ?? [];

        // 2. Obtener memoria permanente del cliente si aplica
        $memories = [];
        if ($customerPhone) {
            $memoriesCollection = $this->memoryRepository->getCustomerMemories($tenantId, $customerPhone);
            $memories = $memoriesCollection->pluck('memory_value', 'memory_key')->toArray();
        }

        $timezone = $knowledge['payload']['business']['timezone'] ?? 'America/Lima';

        return new PromptContextDTO(
            tenantId: $tenantId,
            businessKnowledge: $knowledge,
            recentMessages: $recentMessages,
            customerMemories: $memories,
            timezone: $timezone,
            promptVersion: 'v1.0'
        );
    }
}
