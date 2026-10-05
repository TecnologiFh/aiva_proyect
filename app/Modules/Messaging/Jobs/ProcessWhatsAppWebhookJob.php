<?php

namespace App\Modules\Messaging\Jobs;

use App\Modules\Messaging\DTOs\IncomingWhatsAppMessageDTO;
use App\Modules\Messaging\Services\ConversationOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly IncomingWhatsAppMessageDTO $incomingMessage,
        public readonly int $tenantId
    ) {}

    public function handle(ConversationOrchestrator $orchestrator): void
    {
        $orchestrator->handleIncomingMessage($this->incomingMessage, $this->tenantId);
    }
}
