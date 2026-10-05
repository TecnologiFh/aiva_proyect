<?php

namespace App\Modules\AI\DTOs;

class PromptContextDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly array $businessKnowledge,
        public readonly array $recentMessages = [],
        public readonly array $customerMemories = [],
        public readonly string $timezone = 'America/Lima',
        public readonly string $promptVersion = 'v1.0'
    ) {}
}
