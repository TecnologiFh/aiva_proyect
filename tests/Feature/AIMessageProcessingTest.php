<?php

namespace Tests\Feature;

use App\Core\Enums\CustomerIntent;
use App\Modules\AI\DTOs\PromptContextDTO;
use App\Modules\AI\Providers\MockAIProvider;
use Tests\TestCase;

class AIMessageProcessingTest extends TestCase
{
    public function test_mock_ai_provider_detects_purchase_intent(): void
    {
        $provider = new MockAIProvider();
        $context = new PromptContextDTO(
            tenantId: 1,
            businessKnowledge: ['payload' => ['business' => ['name' => 'Donuts Fs']]]
        );

        $result = $provider->generateResponse($context, 'Quiero pedir 2 donas de chocolate');

        $this->assertEquals(CustomerIntent::PURCHASE_INTENT->value, $result->intent);
        $this->assertGreaterThanOrEqual(0.90, $result->confidence);
        $this->assertFalse($result->needsHuman);
        $this->assertEquals('MOCK', $result->provider);
        $this->assertStringContainsString('Producto Detectado', $result->replyText);
    }
}
