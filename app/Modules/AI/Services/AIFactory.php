<?php

namespace App\Modules\AI\Services;

use App\Core\Contracts\AI\AIProviderInterface;
use App\Modules\AI\Providers\GeminiProvider;
use App\Modules\AI\Providers\MockAIProvider;
use App\Modules\AI\Providers\OpenAIProvider;

class AIFactory
{
    public function __construct(
        private readonly PromptBuilderService $promptBuilder
    ) {}

    public function make(?string $driver = null): AIProviderInterface
    {
        $selectedDriver = strtoupper($driver ?? config('services.ai.default', env('AI_PROVIDER', 'MOCK')));

        return match ($selectedDriver) {
            'OPENAI' => new OpenAIProvider($this->promptBuilder),
            'GEMINI' => new GeminiProvider($this->promptBuilder),
            default => new MockAIProvider(),
        };
    }
}
