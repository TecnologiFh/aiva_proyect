<?php

namespace App\Modules\Messaging\Jobs;

use App\Http\Controllers\AIPlaygroundController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAiMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 60;

    public int $tenantId;
    public string $userMessage;
    public ?string $phone;
    public ?string $correlationId;

    public function __construct(int $tenantId, string $userMessage, ?string $phone = null, ?string $correlationId = null)
    {
        $this->tenantId = $tenantId;
        $this->userMessage = $userMessage;
        $this->phone = $phone;
        $this->correlationId = $correlationId ?? (app()->bound('correlation_id') ? app('correlation_id') : null);
    }

    public function handle(): void
    {
        if ($this->correlationId) {
            Log::shareContext(['correlation_id' => $this->correlationId, 'tenant_id' => $this->tenantId]);
        }

        Log::info("Iniciando procesamiento asíncrono de mensaje de IA para Tenant #{$this->tenantId}");

        // Invocación al Gateway de IA
        $aiController = new AIPlaygroundController();
        $response = $aiController->processMessageDirectly($this->tenantId, $this->userMessage);

        Log::info("Mensaje de IA procesado asíncronamente con éxito para Tenant #{$this->tenantId}: " . substr($response['response'] ?? '', 0, 100));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Fallo crítico en ProcessAiMessageJob para Tenant #{$this->tenantId}: " . $exception->getMessage(), [
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId,
            'user_message' => $this->userMessage,
        ]);
    }
}
