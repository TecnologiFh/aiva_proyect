<?php

namespace App\Modules\AI\Providers;

use App\Core\Contracts\AI\AIProviderInterface;
use App\Core\Enums\CustomerIntent;
use App\Modules\AI\DTOs\AIAnalysisResult;
use App\Modules\AI\DTOs\PromptContextDTO;
use Illuminate\Support\Str;

class MockAIProvider implements AIProviderInterface
{
    public function generateResponse(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        $messageLower = Str::lower($userMessage);

        if (Str::contains($messageLower, ['quiero', 'pedir', 'comprar', 'dame', 'ordeno'])) {
            return new AIAnalysisResult(
                intent: CustomerIntent::PURCHASE_INTENT->value,
                confidence: 0.98,
                entities: [
                    ['product' => 'Producto Detectado', 'quantity' => 1]
                ],
                needsHuman: false,
                replyText: '¡Excelente elección! He registrado tu pedido de Producto Detectado. ¿Deseas agregar alguna bebida u opción extra?',
                provider: 'MOCK',
                model: 'mock-v1',
                promptVersion: $context->promptVersion,
                inputTokens: 120,
                outputTokens: 45,
                estimatedCost: 0.000000,
                latencyMs: 15
            );
        }

        if (Str::contains($messageLower, ['cuanto', 'precio', 'costo', 'que tienes', 'menu', 'catalogo'])) {
            return new AIAnalysisResult(
                intent: CustomerIntent::PRODUCT_INQUIRY_INTENT->value,
                confidence: 0.95,
                entities: [],
                needsHuman: false,
                replyText: 'Contamos con una amplia variedad de productos. ¿Te gustaría consultar alguna categoría o especialidad en particular?',
                provider: 'MOCK',
                model: 'mock-v1',
                promptVersion: $context->promptVersion,
                inputTokens: 100,
                outputTokens: 35,
                estimatedCost: 0.000000,
                latencyMs: 12
            );
        }

        if (Str::contains($messageLower, ['horario', 'abierto', 'hora', 'atienden', 'domingo'])) {
            return new AIAnalysisResult(
                intent: CustomerIntent::HOURS_INQUIRY_INTENT->value,
                confidence: 0.96,
                entities: [],
                needsHuman: false,
                replyText: 'Nuestros horarios de atención son regulares durante la semana. ¿Deseas consultar sobre algún día en específico?',
                provider: 'MOCK',
                model: 'mock-v1',
                promptVersion: $context->promptVersion,
                inputTokens: 90,
                outputTokens: 30,
                estimatedCost: 0.000000,
                latencyMs: 10
            );
        }

        return new AIAnalysisResult(
            intent: CustomerIntent::GREETING_INTENT->value,
            confidence: 0.90,
            entities: [],
            needsHuman: false,
            replyText: '¡Hola! Bienvenido a nuestro canal de atención. ¿En qué puedo ayudarte hoy?',
            provider: 'MOCK',
            model: 'mock-v1',
            promptVersion: $context->promptVersion,
            inputTokens: 80,
            outputTokens: 25,
            estimatedCost: 0.000000,
            latencyMs: 8
        );
    }

    public function analyzeIntent(PromptContextDTO $context, string $userMessage): AIAnalysisResult
    {
        return $this->generateResponse($context, $userMessage);
    }

    public function getProviderName(): string
    {
        return 'MOCK';
    }
}
