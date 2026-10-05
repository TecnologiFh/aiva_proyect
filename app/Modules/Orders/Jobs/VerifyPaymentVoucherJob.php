<?php

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Services\PaymentVoucherEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyPaymentVoucherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 60;

    public int $tenantId;
    public int $orderId;
    public string $imageBinary;
    public string $provider;
    public float $extractedAmount;
    public ?string $extractedOpNumber;
    public ?string $extractedPhone;
    public ?string $extractedHolder;
    public ?string $correlationId;

    public function __construct(
        int $tenantId,
        int $orderId,
        string $imageBinary,
        string $provider,
        float $extractedAmount,
        ?string $extractedOpNumber = null,
        ?string $extractedPhone = null,
        ?string $extractedHolder = null,
        ?string $correlationId = null
    ) {
        $this->tenantId = $tenantId;
        $this->orderId = $orderId;
        $this->imageBinary = $imageBinary;
        $this->provider = $provider;
        $this->extractedAmount = $extractedAmount;
        $this->extractedOpNumber = $extractedOpNumber;
        $this->extractedPhone = $extractedPhone;
        $this->extractedHolder = $extractedHolder;
        $this->correlationId = $correlationId ?? (app()->bound('correlation_id') ? app('correlation_id') : null);
    }

    public function handle(): void
    {
        if ($this->correlationId) {
            Log::shareContext(['correlation_id' => $this->correlationId, 'tenant_id' => $this->tenantId]);
        }

        Log::info("Iniciando verificación asíncrona de comprobante para Pedido #{$this->orderId} en Tenant #{$this->tenantId}");

        $engine = new PaymentVoucherEngine();
        $result = $engine->processVoucher(
            $this->tenantId,
            $this->orderId,
            $this->imageBinary,
            $this->provider,
            $this->extractedAmount,
            $this->extractedOpNumber,
            $this->extractedPhone,
            $this->extractedHolder
        );

        Log::info("Comprobante para Pedido #{$this->orderId} verificado asíncronamente con resultado: {$result['status']}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Fallo crítico en VerifyPaymentVoucherJob para Pedido #{$this->orderId}: " . $exception->getMessage(), [
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId,
            'order_id' => $this->orderId,
        ]);
    }
}
