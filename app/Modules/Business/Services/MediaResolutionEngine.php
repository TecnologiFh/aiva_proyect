<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\MediaAsset;
use App\Modules\Business\Models\CatalogVersion;
use Illuminate\Support\Str;

class MediaResolutionEngine
{
    private MediaSemanticSearchService $searchService;
    private ConversationVisualContextService $visualContextService;

    public function __construct()
    {
        $this->searchService = new MediaSemanticSearchService();
        $this->visualContextService = new ConversationVisualContextService();
    }

    /**
     * Resolver recurso visual comercial con ranking lexicográfico determinístico y memoria anafórica.
     */
    public function resolve(int $tenantId, string $query, ?string $conversationId = null): array
    {
        $normalized = Str::of($query)->lower()->ascii()->trim()->squish()->toString();
        $normalized = preg_replace('/([a-z])\1{2,}/i', '$1', $normalized);

        // 1. Obtener versión activa de catálogo
        $activeVersion = CatalogVersion::where('tenant_id', $tenantId)
            ->where('status', 'PUBLISHED')
            ->where('is_active', true)
            ->first();
        $versionId = $activeVersion ? $activeVersion->id : null;

        // 2. Clasificar subtipo de intención REQUEST_MEDIA
        $targetInfo = $this->classifyMediaTarget($normalized);

        // 3. Si es REFERENT ("esa carta", "esa", "la anterior"), resolver contra ConversationVisualContext (AG-51)
        if ($targetInfo['type'] === 'REFERENT' && $conversationId) {
            $referentAsset = $this->visualContextService->resolveAnaphoricReferent($tenantId, $conversationId);
            if ($referentAsset) {
                return $this->buildDispatchPayload($tenantId, $referentAsset, 'CONVERSATIONAL_REFERENT', $conversationId, $targetInfo['target']);
            }
        }

        // 4. Búsqueda semántica lexicográfica mediante MediaSemanticSearchService
        $searchResult = $this->searchService->search($tenantId, $targetInfo['target'], $conversationId, $versionId);

        if ($searchResult['status'] === 'MATCH_EXACT' && $searchResult['asset']) {
            $asset = $searchResult['asset'];
            return $this->buildDispatchPayload($tenantId, $asset, $searchResult['resolution_type'], $conversationId, $targetInfo['target']);
        }

        if ($searchResult['status'] === 'AMBIGUOUS_MEDIA_MATCH') {
            return [
                'status' => 'AMBIGUOUS_MEDIA_MATCH',
                'media_asset' => null,
                'dispatch_allowed' => false,
                'candidates' => $searchResult['candidates'] ?? [],
                'clarification_prompt' => "¿Deseas que te comparta nuestra Carta Especial o la Carta de Promociones? 😊",
            ];
        }

        // 5. Fallback seguro controlado (solo si la consulta era general de carta)
        if ($targetInfo['type'] === 'GENERAL') {
            $generalAsset = MediaAsset::where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->where('is_dispatchable', true)
                ->orderBy('display_order', 'asc')
                ->first();

            if ($generalAsset && $generalAsset->checkIsDispatchable()) {
                return $this->buildDispatchPayload($tenantId, $generalAsset, 'GENERAL_MENU_DEFAULT', $conversationId, 'general');
            }
        }

        return [
            'status' => 'NOT_FOUND',
            'media_asset' => null,
            'dispatch_allowed' => false,
            'message' => "No se encontró un recurso visual disponible para '{$query}'",
        ];
    }

    /**
     * Clasificación granular del target de la solicitud multimedia
     */
    private function classifyMediaTarget(string $query): array
    {
        // Normalización básica y remoción de signos de interrogación/exclamación
        $norm = Str::of($query)->lower()->ascii()->replace(['¿', '?', '¡', '!', ',', '.', ':'], ' ')->trim()->squish()->toString();
        $norm = preg_replace('/([a-z])\1{2,}/i', '$1', $norm);

        // Limpiar saludos iniciales (hola, buenas, etc.)
        $normWithoutGreeting = trim(preg_replace('/^(hola|buenos dias|buenas tardes|buenas noches|que tal|buenas)\s*/i', '', $norm));
        if (!empty($normWithoutGreeting)) {
            $norm = $normWithoutGreeting;
        }

        // A. Referentes Anafóricos
        if (preg_match('/\b(esa|esa carta|la anterior|mandamela|mandamela otra vez|verla de nuevo|vuelvemela a mandar)\b/i', $norm)) {
            return ['type' => 'REFERENT', 'target' => 'REFERENT'];
        }

        // B. Carta General
        if (preg_match('/^(pasame la carta|la carta|menu|el menu|carta|ver carta|muestrame la carta|tienes carta)$/i', $norm)) {
            return ['type' => 'GENERAL', 'target' => 'carta'];
        }

        // C. Limpiar preámbulos, muletillas, preguntas y palabras auxiliares
        $cleaned = preg_replace('/\b(mandame|pasame|muestrame|ensename|quiero ver|tienes|donde estan|donde esta|donde venden|la carta donde estan|la carta donde esta|la carta de|carta de|aquella que tiene|la que tiene|la carta que tiene|cual carta tiene|en que carta estan|en que carta esta|los sabores de|sabores de|seccion de|la de|los de|las|los|la|el|de|me|por favor)\b/i', ' ', $norm);
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if (!empty($cleaned) && $cleaned !== 'carta' && $cleaned !== 'menu') {
            return ['type' => 'CATEGORY', 'target' => $cleaned];
        }

        return ['type' => 'GENERAL', 'target' => 'carta'];
    }

    /**
     * Ensamblar contrato de entrega con caption conciso y registro en ConversationVisualContext
     */
    private function buildDispatchPayload(int $tenantId, MediaAsset $asset, string $resolutionType, ?string $conversationId, string $target): array
    {
        $isGeneral = ($resolutionType === 'GENERAL_MENU_DEFAULT' || $target === 'general' || ($asset->display_order === 1 && $asset->category === 'CARTA_GENERAL'));
        $captionPolicy = new \App\Modules\Messaging\Services\CaptionPolicy();
        $tenantName = '';
        try {
            $tenant = \App\Modules\Identity\Models\Tenant::find($tenantId);
            $tenantName = $tenant->name ?? '';
        } catch (\Throwable $e) {}

        $targetLabel = ($target !== 'general' && $resolutionType !== 'GENERAL_MENU_DEFAULT') ? $target : null;
        $caption = $captionPolicy->generateCaption(
            assetTitle: $asset->title,
            targetLabel: $targetLabel,
            isMenu: true,
            isGeneralMenu: $isGeneral,
            tenantName: $tenantName
        );

        if ($conversationId) {
            $this->visualContextService->recordDispatchedMedia(
                $tenantId,
                $conversationId,
                $asset->id,
                $target,
                $caption,
                $asset->category
            );
        }

        return [
            'status' => 'MATCH_EXACT',
            'media_asset' => $asset,
            'media_id' => $asset->id,
            'commercial_media_id' => $asset->commercial_media_id ?? $asset->id,
            'title' => $asset->title,
            'category' => $asset->category,
            'file_path' => $asset->file_path,
            'mime_type' => $asset->mime_type,
            'caption' => $caption,
            'resolution_type' => $resolutionType,
            'dispatch_allowed' => true,
            'is_terminal_dispatch' => true, // AG-37.1 STOP
        ];
    }
}
