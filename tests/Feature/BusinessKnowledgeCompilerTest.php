<?php

namespace Tests\Feature;

use App\Modules\Business\DTOs\CompiledBusinessKnowledge;

use Tests\TestCase;

class BusinessKnowledgeCompilerTest extends TestCase
{
    public function test_compiled_business_knowledge_dto_structure(): void
    {
        $dto = new CompiledBusinessKnowledge(
            tenantId: 10,
            schemaVersion: 1,
            knowledgeVersion: 5,
            hash: 'a1b2c3d4e5f6',
            payload: [
                'business' => ['name' => 'Donuts Fs'],
                'products' => [],
            ],
            generatedAt: '2026-08-05T22:00:00Z'
        );

        $array = $dto->toArray();

        $this->assertEquals(10, $array['metadata']['tenant_id']);
        $this->assertEquals(1, $array['metadata']['schema_version']);
        $this->assertEquals(5, $array['metadata']['knowledge_version']);
        $this->assertEquals('a1b2c3d4e5f6', $array['metadata']['hash']);
        $this->assertEquals('Donuts Fs', $array['payload']['business']['name']);
    }
}
