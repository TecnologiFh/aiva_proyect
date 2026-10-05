<?php

namespace Tests\Feature;

use App\Modules\AI\DTOs\PromptContextDTO;
use App\Modules\AI\Services\PromptBuilderService;

use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    public function test_prompt_builder_service_formats_system_prompt(): void
    {
        $service = new PromptBuilderService();
        $context = new PromptContextDTO(
            tenantId: 1,
            businessKnowledge: [
                'payload' => [
                    'business' => ['name' => 'Donuts Fs', 'business_type' => 'BAKERY', 'currency' => 'PEN'],
                    'products' => [
                        [
                            'name' => 'Dona Chocolate',
                            'category' => 'Postres',
                            'type' => 'PRODUCT',
                            'base_price' => 8.50,
                            'is_available' => true,
                        ]
                    ]
                ]
            ]
        );

        $prompt = $service->buildSystemPrompt($context);

        $this->assertStringContainsString("Donuts Fs", $prompt);
        $this->assertStringContainsString("BAKERY", $prompt);
        $this->assertStringContainsString("Dona Chocolate", $prompt);
        $this->assertStringContainsString("PURCHASE_INTENT", $prompt);
    }
}
