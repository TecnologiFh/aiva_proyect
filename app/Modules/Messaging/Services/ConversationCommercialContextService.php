<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Messaging\Models\ConversationCommercialContext;
use Illuminate\Support\Facades\DB;

class ConversationCommercialContextService
{
    public function getContext(int $tenantId, string $conversationId): ?ConversationCommercialContext
    {
        return ConversationCommercialContext::where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function updateContext(
        int $tenantId,
        string $conversationId,
        int $catalogVersionId,
        int $commercialMediaId,
        ?string $category,
        int $displayOrder
    ): ConversationCommercialContext {
        // Enforce Rule 6: commercialMediaId must belong to catalogVersionId
        $belongs = DB::table('catalog_version_media')
            ->where('tenant_id', $tenantId)
            ->where('catalog_version_id', $catalogVersionId)
            ->where('commercial_media_id', $commercialMediaId)
            ->exists();

        if (!$belongs) {
            throw new \InvalidArgumentException("Context Referential Integrity Violated: Media {$commercialMediaId} does not belong to Catalog Version {$catalogVersionId}.");
        }

        return ConversationCommercialContext::updateOrCreate(
            ['tenant_id' => $tenantId, 'conversation_id' => $conversationId],
            [
                'catalog_version_id' => $catalogVersionId,
                'last_commercial_media_id' => $commercialMediaId,
                'last_category' => $category,
                'last_display_order' => $displayOrder,
            ]
        );
    }

    public function invalidateContextForNewVersion(int $tenantId, int $newCatalogVersionId): void
    {
        // When a new catalog is published, reset existing contexts for that tenant to the new version
        ConversationCommercialContext::where('tenant_id', $tenantId)
            ->where('catalog_version_id', '!=', $newCatalogVersionId)
            ->update([
                'catalog_version_id' => $newCatalogVersionId,
                'last_commercial_media_id' => null,
                'last_display_order' => 1,
            ]);
    }
}
