<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\CommercialMediaAlias;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Messaging\Models\ConversationCommercialContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommercialMediaResolverService
{
    /**
     * Resuelve un medio comercial determinísticamente a partir del query del cliente y contexto.
     */
    public function resolve(int $tenantId, ?string $query = null, ?string $conversationId = null): array
    {
        $normalizedQuery = $this->normalizeString($query ?? '');

        // 1. Obtener catálogo activo publicado del tenant (C13: exactamente uno activo)
        $activeCatalogVersion = DB::table('catalog_versions')
            ->where('tenant_id', $tenantId)
            ->where(function($q) {
                $q->where('status', 'PUBLISHED')
                  ->orWhere('is_active', 1);
            })
            ->orderBy('id', 'desc')
            ->first();

        $catalogVersionId = $activeCatalogVersion ? $activeCatalogVersion->id : null;

        // 2. Verificar navegación relativa conversacional ("la siguiente", "la anterior", "la última", etc.)
        $relativeIntent = $this->detectRelativeNavigationIntent($normalizedQuery);

        if ($relativeIntent && $conversationId) {
            $navResult = $this->resolveRelativeNavigation($tenantId, $conversationId, $relativeIntent);
            if ($navResult['status'] === 'MATCH_EXACT') {
                return $this->buildDispatchContract($tenantId, $navResult['media'], 'MATCH_EXACT', $conversationId, $catalogVersionId);
            }
        }

        // 3. Consulta de número ordinal específico ("primera carta", "segunda", "carta 4")
        $ordinalOrder = $this->detectOrdinalCardOrder($normalizedQuery);
        if ($ordinalOrder !== null && $catalogVersionId) {
            $media = CommercialMedia::where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->where('display_order', $ordinalOrder)
                ->whereHas('catalogVersions', function($q) use ($catalogVersionId) {
                    $q->where('catalog_versions.id', $catalogVersionId);
                })
                ->first();

            if ($media) {
                return $this->buildDispatchContract($tenantId, $media, 'MATCH_EXACT', $conversationId, $catalogVersionId);
            }
        }

        // 4. Búsqueda por categoría o alias específico
        $aliasMatch = $this->resolveByAliases($tenantId, $normalizedQuery, $catalogVersionId);
        if ($aliasMatch) {
            if ($aliasMatch['match_type'] === 'MATCH_AMBIGUOUS') {
                return [
                    'match_type' => 'MATCH_AMBIGUOUS',
                    'candidates' => $aliasMatch['candidates'],
                    'dispatch_allowed' => false,
                    'message' => 'Consulta ambigua entre múltiples cartas comerciales.',
                ];
            }
            return $this->buildDispatchContract($tenantId, $aliasMatch['media'], 'MATCH_EXACT', $conversationId, $catalogVersionId);
        }

        // 5. Consulta general por la carta ("pásame la carta", "la carta", "el menú") -> MIN(display_order)
        if ($this->isGeneralMenuQuery($normalizedQuery) && $catalogVersionId) {
            $media = CommercialMedia::where('tenant_id', $tenantId)
                ->where('status', 'ACTIVE')
                ->whereHas('catalogVersions', function($q) use ($catalogVersionId) {
                    $q->where('catalog_versions.id', $catalogVersionId);
                })
                ->orderBy('display_order', 'asc')
                ->first();

            if ($media) {
                return $this->buildDispatchContract($tenantId, $media, 'MATCH_EXACT', $conversationId, $catalogVersionId);
            }
        }

        return [
            'match_type' => 'NOT_FOUND',
            'media' => null,
            'dispatch_allowed' => false,
            'message' => 'No se encontró medio comercial disponible.',
        ];
    }

    /**
     * Detección de intenciones de navegación relativa conversacional.
     */
    private function detectRelativeNavigationIntent(string $query): ?string
    {
        if (preg_match('/\b(la siguiente|siguiente|la otra|proxima|otra carta|pasa la otra)\b/i', $query)) {
            return 'NEXT';
        }
        if (preg_match('/\b(la anterior|anterior|la previa|previa|esa no, mandame la anterior|antes)\b/i', $query)) {
            return 'PREV';
        }
        if (preg_match('/\b(la ultima|ultima|la final|el final)\b/i', $query)) {
            return 'LAST';
        }
        if (preg_match('/\b(la primera|primera|la inicial|volver al inicio)\b/i', $query)) {
            return 'FIRST';
        }
        return null;
    }

    /**
     * Detección de peticiones ordinales directas sobre cartas ("carta 1", "carta 4", "primera carta", "segunda carta").
     */
    private function detectOrdinalCardOrder(string $query): ?int
    {
        $q = preg_replace('/([a-z])\1{2,}/i', '$1', $query);
        if (preg_match('/\b(primera carta|carta 1|carta uno|la 1|numero 1|la numero 1)\b/i', $q)) return 1;
        if (preg_match('/\b(segunda carta|carta 2|carta dos|la 2|numero 2|la numero 2)\b/i', $q)) return 2;
        if (preg_match('/\b(tercera carta|carta 3|carta tres|la 3|numero 3|la numero 3)\b/i', $q)) return 3;
        if (preg_match('/\b(cuarta carta|carta 4|carta cuatro|la 4|numero 4|la numero 4)\b/i', $q)) return 4;
        return null;
    }

    /**
     * Comprueba si es una petición general de carta.
     */
    private function isGeneralMenuQuery(string $query): bool
    {
        $q = preg_replace('/([a-z])\1{2,}/i', '$1', $query);
        return (bool) preg_match('/\b(carta|menu|la carta|pasame la carta|ver carta|muestrame la carta|el menu)\b/i', $q);
    }

    /**
     * Resolución de navegación relativa acotada a la versión de catálogo (Version-Pinned Navigation - C4).
     */
    public function resolveRelativeNavigation(int $tenantId, string $conversationId, string $intent): array
    {
        $context = ConversationCommercialContext::where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->first();

        if (!$context) {
            return ['status' => 'NO_CONTEXT'];
        }

        $catalogVersionId = $context->catalog_version_id;
        $lastOrder = $context->last_display_order;

        // Invariante C4 / Regla 6: last_commercial_media_id debe pertenecer a catalog_version_id
        if ($context->last_commercial_media_id) {
            $belongs = DB::table('catalog_version_media')
                ->where('tenant_id', $tenantId)
                ->where('catalog_version_id', $catalogVersionId)
                ->where('commercial_media_id', $context->last_commercial_media_id)
                ->exists();

            if (!$belongs) {
                // Violación de integridad referencial del contexto (Regla 6)
                return ['status' => 'CONTEXT_REFERENTIAL_INTEGRITY_VIOLATION'];
            }
        }

        $query = CommercialMedia::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->whereHas('catalogVersions', function($q) use ($catalogVersionId) {
                $q->where('catalog_versions.id', $catalogVersionId);
            });

        $media = null;

        switch ($intent) {
            case 'NEXT':
                $media = (clone $query)->where('display_order', '>', $lastOrder)
                    ->orderBy('display_order', 'asc')
                    ->first();
                break;
            case 'PREV':
                $media = (clone $query)->where('display_order', '<', $lastOrder)
                    ->orderBy('display_order', 'desc')
                    ->first();
                break;
            case 'LAST':
                $media = (clone $query)->orderBy('display_order', 'desc')->first();
                break;
            case 'FIRST':
                $media = (clone $query)->orderBy('display_order', 'asc')->first();
                break;
        }

        if ($media) {
            return ['status' => 'MATCH_EXACT', 'media' => $media];
        }

        return ['status' => 'OUT_OF_BOUNDS'];
    }

    /**
     * Resolución mediante aliases o categorías registradas.
     */
    private function resolveByAliases(int $tenantId, string $query, ?int $catalogVersionId): ?array
    {
        $mediaList = CommercialMedia::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->when($catalogVersionId, function($q) use ($catalogVersionId) {
                $q->whereHas('catalogVersions', function($subQ) use ($catalogVersionId) {
                    $subQ->where('catalog_versions.id', $catalogVersionId);
                });
            })
            ->with(['aliases'])
            ->get();

        $scored = [];

        foreach ($mediaList as $media) {
            $score = 0;
            $categoryNorm = $this->normalizeString($media->category ?? '');
            $titleNorm = $this->normalizeString($media->title ?? '');

            // Coincidencia con categoría directa
            if (!empty($categoryNorm) && str_contains($query, $categoryNorm)) {
                $score += 10;
            }

            // Coincidencia con aliases
            foreach ($media->aliases as $aliasObj) {
                $aliasNorm = $this->normalizeString($aliasObj->alias);
                if (!empty($aliasNorm) && (str_contains($query, $aliasNorm) || str_contains($aliasNorm, $query))) {
                    $score += (float) ($aliasObj->weight * 5);
                }
            }

            // Coincidencia en título
            if (!empty($titleNorm) && str_contains($query, $titleNorm)) {
                $score += 3;
            }

            if ($score > 0) {
                $scored[] = ['media' => $media, 'score' => $score];
            }
        }

        if (empty($scored)) {
            return null;
        }

        // Ordenar por puntuación descendente
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        if (count($scored) > 1 && $scored[0]['score'] === $scored[1]['score']) {
            return [
                'match_type' => 'MATCH_AMBIGUOUS',
                'candidates' => array_map(fn($item) => $item['media'], array_slice($scored, 0, 3)),
            ];
        }

        return [
            'match_type' => 'MATCH_EXACT',
            'media' => $scored[0]['media'],
        ];
    }

    /**
     * Construcción y validación del contrato conjunto de despacho (Regla C2 + Regla 10).
     */
    private function buildDispatchContract(int $tenantId, CommercialMedia $media, string $matchType, ?string $conversationId, ?int $catalogVersionId): array
    {
        // Validación Tenant-Bound (Regla 10)
        if ($media->tenant_id !== $tenantId) {
            return [
                'match_type' => 'CROSS_TENANT_VIOLATION',
                'media' => null,
                'dispatch_allowed' => false,
                'error_code' => 'TENANT_MISMATCH',
            ];
        }

        // Regla Conjunta C2: media ACTIVE + catalog_version PUBLISHED
        $isMediaActive = ($media->status === 'ACTIVE');

        $isCatalogPublished = DB::table('catalog_version_media')
            ->join('catalog_versions', 'catalog_versions.id', '=', 'catalog_version_media.catalog_version_id')
            ->where('catalog_version_media.commercial_media_id', $media->id)
            ->where('catalog_version_media.tenant_id', $tenantId)
            ->where(function($q) {
                $q->where('catalog_versions.status', 'PUBLISHED')
                  ->orWhere('catalog_versions.is_active', 1);
            })
            ->exists();

        $dispatchAllowed = ($isMediaActive && $isCatalogPublished);

        // Si se autoriza el despacho y hay conversation_id, actualizar la memoria conversacional
        if ($dispatchAllowed && $conversationId && $catalogVersionId) {
            ConversationCommercialContext::updateOrCreate(
                ['tenant_id' => $tenantId, 'conversation_id' => $conversationId],
                [
                    'catalog_version_id' => $catalogVersionId,
                    'last_commercial_media_id' => $media->id,
                    'last_category' => $media->category,
                    'last_display_order' => $media->display_order,
                ]
            );
        }

        return [
            'match_type' => $matchType,
            'media' => $media,
            'dispatch_allowed' => $dispatchAllowed,
            'error_code' => $dispatchAllowed ? null : ($isMediaActive ? 'CATALOG_NOT_PUBLISHED' : 'MEDIA_NOT_ACTIVE'),
        ];
    }

    private function normalizeString(string $text): string
    {
        $text = str_replace(['_', '-'], ' ', $text);
        $unaccented = strtr(mb_strtolower($text, 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
        return trim(preg_replace('/[^a-z0-9\s]/', '', $unaccented));
    }
}
