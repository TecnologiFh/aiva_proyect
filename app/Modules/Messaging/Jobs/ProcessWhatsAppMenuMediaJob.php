<?php

namespace App\Modules\Messaging\Jobs;

use App\Modules\Messaging\Services\MenuVisionAnalyzerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppMenuMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $messageId,
        public readonly string $mediaId
    ) {}

    public function handle(MenuVisionAnalyzerService $analyzer): void
    {
        // 1. Transicionar estado en inbound_webhook_events a PROCESSING
        DB::table('inbound_webhook_events')
            ->where('tenant_id', $this->tenantId)
            ->where('whatsapp_message_id', $this->messageId)
            ->update([
                'status' => 'PROCESSING',
                'updated_at' => now(),
            ]);

        try {
            $result = $analyzer->processWhatsAppMenuMedia($this->tenantId, $this->mediaId);

            $newStatus = ($result['status'] === 'SUCCESS') ? 'PROCESSED' : 'FAILED';

            DB::table('inbound_webhook_events')
                ->where('tenant_id', $this->tenantId)
                ->where('whatsapp_message_id', $this->messageId)
                ->update([
                    'status' => $newStatus,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

        } catch (\Throwable $e) {
            Log::error("ProcessWhatsAppMenuMediaJob Exception: " . $e->getMessage());

            DB::table('inbound_webhook_events')
                ->where('tenant_id', $this->tenantId)
                ->where('whatsapp_message_id', $this->messageId)
                ->update([
                    'status' => 'FAILED',
                    'updated_at' => now(),
                ]);
        }
    }
}
