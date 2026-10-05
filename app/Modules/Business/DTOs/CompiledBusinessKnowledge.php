<?php

namespace App\Modules\Business\DTOs;

class CompiledBusinessKnowledge
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $schemaVersion,
        public readonly int $knowledgeVersion,
        public readonly string $hash,
        public readonly array $payload,
        public readonly string $generatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'metadata' => [
                'tenant_id' => $this->tenantId,
                'schema_version' => $this->schemaVersion,
                'knowledge_version' => $this->knowledgeVersion,
                'hash' => $this->hash,
                'generated_at' => $this->generatedAt,
            ],
            'payload' => $this->payload,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
