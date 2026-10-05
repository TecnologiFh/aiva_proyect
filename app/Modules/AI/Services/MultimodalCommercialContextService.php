<?php

namespace App\Modules\AI\Services;

use App\Modules\Business\Models\MediaAsset;
use App\Modules\Business\Models\MediaVisualRegion;
use App\Modules\Business\Models\ConversationVisualContext;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Services\VisualMemoryService;
use App\Modules\Business\Services\VisualToCommercialReconciliationService;

class MultimodalCommercialContextService
{
    private VisualMemoryService $visualMemory;

    public function __construct()
    {
        $this->visualMemory = new VisualMemoryService();
    }

    /**
     * Construye el contexto coordinado multimodal comercial para el agente.
     * Runtime Vision Calls = 0, Runtime OCR Calls = 0 (Pixel Firewall Activo).
     */
    public function buildContext(int $tenantId, ?string $conversationId = null): array
    {
        // 1. Commercial Context (MySQL Authority)
        $products = Product::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->where('is_available', 1)
            ->get(['id', 'name', 'normalized_name', 'base_price', 'status']);

        $commercialContext = [
            'authorized_products_count' => $products->count(),
            'products' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float)$p->base_price,
            ])->toArray(),
        ];

        // 2. Visual Context (Memoria Visual Persistente)
        $assets = MediaAsset::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->where('is_dispatchable', true)
            ->orderBy('display_order', 'asc')
            ->get();

        $visualCards = [];
        foreach ($assets as $a) {
            $regions = MediaVisualRegion::where('tenant_id', $tenantId)
                ->where('media_asset_id', $a->id)
                ->pluck('label')
                ->toArray();

            $visualCards[] = [
                'asset_id' => $a->id,
                'title' => $a->title,
                'category' => $a->category,
                'display_order' => $a->display_order,
                'sections' => $regions,
            ];
        }

        // 3. Conversation Visual Context (Referentes Anafóricos)
        $conversationVisualContext = null;
        if ($conversationId) {
            $cvc = ConversationVisualContext::where('tenant_id', $tenantId)
                ->where('conversation_id', $conversationId)
                ->first();

            if ($cvc) {
                $conversationVisualContext = [
                    'last_media_asset_id' => $cvc->last_media_asset_id,
                    'last_media_target' => $cvc->last_media_target,
                    'last_media_caption' => $cvc->last_media_caption,
                    'last_visual_referent' => $cvc->last_visual_referent,
                ];
            }
        }

        return [
            'commercial_context' => $commercialContext,
            'visual_context' => [
                'total_visual_cards' => count($visualCards),
                'available_cards' => $visualCards,
            ],
            'conversation_visual_context' => $conversationVisualContext,
            'authority_state' => [
                'supreme_authority' => 'MYSQL',
                'pixel_firewall' => 'ACTIVE',
                'runtime_pixel_calls' => 0,
                'rule_ag40' => 'Visual evidence never overrides commercial MySQL prices or stock.',
                'rule_ag45' => 'VISUAL_ONLY items can be visually located via REQUEST_MEDIA, but cannot be sold.',
            ],
        ];
    }
}
