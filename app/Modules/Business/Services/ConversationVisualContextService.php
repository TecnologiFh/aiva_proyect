<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\ConversationVisualContext;
use App\Modules\Business\Models\MediaAsset;

class ConversationVisualContextService
{
    /**
     * Registrar el activo visual entregado al cliente en la sesión conversacional.
     */
    public function recordDispatchedMedia(
        int $tenantId,
        string $conversationId,
        int $mediaAssetId,
        string $target,
        ?string $caption = null,
        ?string $category = null
    ): ConversationVisualContext {
        return ConversationVisualContext::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
            ],
            [
                'last_media_asset_id' => $mediaAssetId,
                'last_media_target' => $target,
                'last_media_category' => $category,
                'last_media_caption' => $caption,
                'last_visual_referent' => $target,
                'last_media_dispatched_at' => now(),
            ]
        );
    }

    /**
     * Resolver la carta a la que el cliente hace referencia anafórica ("esa", "esa carta", "la anterior").
     * Garantía Constitucional AG-51.
     */
    public function resolveAnaphoricReferent(int $tenantId, string $conversationId): ?MediaAsset
    {
        $ctx = ConversationVisualContext::where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->first();

        if (!$ctx || !$ctx->last_media_asset_id) {
            return null;
        }

        $asset = MediaAsset::where('tenant_id', $tenantId)
            ->where('id', $ctx->last_media_asset_id)
            ->first();

        return ($asset && $asset->checkIsDispatchable()) ? $asset : null;
    }
}
