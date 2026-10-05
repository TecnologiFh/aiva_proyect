<?php

namespace App\Modules\Business\DTOs;

class ProvenanceDTO
{
    public function __construct(
        public readonly string $sourceFile,
        public readonly int $sourceRow,
        public readonly ?int $schemaProfileId = null,
        public readonly ?int $catalogVersionId = null,
        public readonly array $sourceValues = []
    ) {}

    public function toArray(): array
    {
        return [
            'source_file' => $this->sourceFile,
            'source_row' => $this->sourceRow,
            'schema_profile_id' => $this->schemaProfileId,
            'catalog_version_id' => $this->catalogVersionId,
            'source_values' => $this->sourceValues,
        ];
    }
}
