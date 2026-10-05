<?php

namespace App\Core\Contracts\AI;

use App\Modules\AI\DTOs\AIAnalysisResult;
use App\Modules\AI\DTOs\PromptContextDTO;

interface AIProviderInterface
{
    /**
     * Procesa un mensaje de usuario con el contexto del negocio y genera una salida estructurada.
     */
    public function generateResponse(PromptContextDTO $context, string $userMessage): AIAnalysisResult;

    /**
     * Realiza un análisis liviano para detectar únicamente la intención comercial y entidades del mensaje.
     */
    public function analyzeIntent(PromptContextDTO $context, string $userMessage): AIAnalysisResult;

    /**
     * Retorna el nombre identificador del proveedor (ej. OPENAI, GEMINI, MOCK).
     */
    public function getProviderName(): string;
}
