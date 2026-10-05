<?php

namespace App\Modules\Messaging\Services;

use App\Core\Contracts\AI\VisionProviderInterface;
use App\Modules\AI\Services\MenuIngestionGuardrailService;
use App\Modules\Business\Services\MenuIngestionEngine;
use App\Modules\Messaging\Services\WhatsAppMediaDownloaderService;
use Illuminate\Support\Facades\Log;

class MenuVisionAnalyzerService
{
    public function __construct(
        private readonly WhatsAppMediaDownloaderService $downloader,
        private readonly VisionProviderInterface $visionProvider,
        private readonly MenuIngestionGuardrailService $guardrailService,
        private readonly MenuIngestionEngine $ingestionEngine
    ) {}

    /**
     * Orquestación P3.10-B: WhatsApp Media -> Visión AI -> Guardrails -> MySQL -> Redis POST-COMMIT.
     */
    public function processWhatsAppMenuMedia(int $tenantId, string $mediaId): array
    {
        // 1. Descarga de imagen desde Meta Graph API
        $downloadResult = $this->downloader->downloadMedia($tenantId, $mediaId);
        if ($downloadResult['status'] !== 'SUCCESS') {
            return [
                'status' => 'FAILED',
                'stage' => 'DOWNLOAD',
                'reason' => $downloadResult['reason'] ?? 'Failed to download WhatsApp media.',
            ];
        }

        // 2. Visión Multi-Modal (P3.10-A Fallback Vision Provider)
        $extractionDTO = $this->visionProvider->analyzeMenuImage($downloadResult['base64'], $downloadResult['mime']);
        if ($extractionDTO->isTechnicalFailure()) {
            return [
                'status' => 'FAILED',
                'stage' => 'VISION_AI',
                'reason' => 'Technical failure on vision providers. Catalog remains unchanged.',
            ];
        }

        // 3. Guardrail Evaluation (P3.10-A)
        $guardrailResult = $this->guardrailService->evaluateGuardrail($extractionDTO);
        if (!$guardrailResult->isIngestable()) {
            return [
                'status' => 'BLOCKED_BY_GUARDRAIL',
                'stage' => 'GUARDRAIL',
                'reason' => $guardrailResult->reasons[0] ?? 'Menu extraction rejected by guardrail.',
                'rejected_items' => count($guardrailResult->rejectedItems),
            ];
        }

        // 4. Persistence & Redis Cache Recompilation strictly POST-COMMIT (P3.10-A)
        $ingestionResult = $this->ingestionEngine->ingestValidatedMenu($tenantId, $guardrailResult);

        $createdProds = $ingestionResult['created_products'] ?? 0;
        $updatedProds = $ingestionResult['updated_products'] ?? 0;
        $totalProds = $createdProds + $updatedProds;

        $replyMessage = "¡Muchas gracias! Hemos procesado tu carta correctamente. Se actualizaron {$totalProds} productos en nuestro menú digital en tiempo real.";

        return [
            'status' => 'SUCCESS',
            'stage' => 'COMPLETED',
            'tenant_id' => $tenantId,
            'media_id' => $mediaId,
            'ingestion_result' => $ingestionResult,
            'customer_reply' => $replyMessage,
        ];
    }
}
