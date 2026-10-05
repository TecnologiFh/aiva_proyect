<?php

namespace App\Modules\AI\Services;

use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\ProductVisualReference;
use Illuminate\Support\Facades\DB;

class AIContextCatalogProjectorService
{
    /**
     * Proyecta el prompt comercial autorizado únicamente desde el Catálogo Canónico Publicado (RF-06, RF-08, RF-10, P3.11, AG-34, AG-35).
     * Regla estricta: NEVER_ASSERT_UNSUPPORTED_CATALOG_FACT y NO_CROSS_PRODUCT_SUBSTITUTION.
     */
    public function projectAIContext(int $tenantId): string
    {
        // 1. Prioridad: Catálogo Canónico P3.11 Multi-Fuente (catalog_versions)
        $publishedVersion = CatalogVersion::where('tenant_id', $tenantId)
            ->where('status', 'PUBLISHED')
            ->where('is_active', 1)
            ->orderByDesc('version_number')
            ->first();

        if ($publishedVersion) {
            $linkedMediaIds = CatalogVersionMedia::where('catalog_version_id', $publishedVersion->id)
                ->where('tenant_id', $tenantId)
                ->pluck('commercial_media_id')
                ->toArray();

            $mediaList = CommercialMedia::whereIn('id', $linkedMediaIds)
                ->where('tenant_id', $tenantId)
                ->orderBy('display_order')
                ->get();

            if ($mediaList->isNotEmpty()) {
                $promptLines = [];
                $promptLines[] = "=== CATÁLOGO CANÓNICO AUTORIZADO (v{$publishedVersion->version_number} - P3.11 MYSQL AUTHORITY) ===";

                foreach ($mediaList as $media) {
                    $categoryTitle = strtoupper(trim($media->title ?: ($media->category ?? 'SECCIÓN')));
                    $promptLines[] = "\n▶ {$categoryTitle}:";

                    $pvrs = ProductVisualReference::where('commercial_media_id', $media->id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'PUBLISHED')
                        ->with(['product', 'variant.product'])
                        ->orderBy('display_index')
                        ->get();

                    if ($pvrs->isEmpty()) {
                        // Fallback por categoría en products
                        $prods = DB::table('products')->where('tenant_id', $tenantId)->get();
                        foreach ($prods as $p) {
                            $price = number_format((float)($p->price ?? $p->base_price ?? 0), 2);
                            $promptLines[] = "  - {$p->name}: S/ {$price}";
                        }
                    } else {
                        foreach ($pvrs as $pvr) {
                            $product = $pvr->product ?? ($pvr->variant ? $pvr->variant->product : null);
                            $variant = $pvr->variant;
                            if (!$product && !$variant) continue;

                            $name = $product ? $product->name : ($variant->sku ?? 'Producto');
                            $priceVal = $variant ? $variant->effective_price : (float)($product->base_price ?? 0);
                            $priceStr = number_format($priceVal, 2);

                            $qualifierStr = "";
                            if ($variant && !empty($variant->color)) {
                                $qualifierStr .= " ({$variant->color})";
                            }

                            $promptLines[] = "  - {$name}{$qualifierStr}: S/ {$priceStr}";
                        }
                    }
                }

                $promptLines[] = "\n=== REGLAS CONSTITUCIONALES DE RIGOR COMERCIAL (AG-34, AG-35) ===";
                $promptLines[] = "1. AUTORIDAD EXCLUSIVA DE MYSQL: Todos los precios oficiales provienen estrictamente de la lista autorizada arriba. NUNCA inventes platos ni afirmes precios que no estén registrados en MySQL.";
                $promptLines[] = "2. PROHIBICIÓN ABSOLUTA DE SUSTITUCIÓN CRUZADA (AG-35): Queda TERMINANTEMENTE PROHIBIDO sustituir un producto por otro de diferente tamaño, precio o composición. Si el cliente pregunta por 'Parrilla Familiar', responde con la opción oficial 'Parrilla Familiar' (S/ 150.00). Si consultara por una opción que NO está registrada (ej: 'Parrilla Imperial'), responde amablemente que no dispones de esa opción en tu carta oficial, en lugar de ofrecer otra haciéndola pasar por la solicitada (NUNCA digas que la Parrilla Familiar es la Parrilla para Dos).";
                $promptLines[] = "3. MANEJO DE AMBIGÜEDAD: Si el cliente pide un plato con opciones múltiples sin especificar (ejemplo: 'Chaufa de Pollo' existiendo Personal y Familiar), pregúntale amablemente cuál de las opciones prefiere.";

                return implode("\n", $promptLines);
            }
        }

        // 2. Fallback Retrocompatible: canonical_catalogs (P3.10)
        $publishedCatalog = DB::table('canonical_catalogs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'PUBLISHED')
            ->orderByDesc('version_number')
            ->first();

        if (!$publishedCatalog || empty($publishedCatalog->payload_json)) {
            return "NO_ACTIVE_CATALOG_AVAILABLE";
        }

        $payload = json_decode($publishedCatalog->payload_json, true);
        $menuGroups = $payload['menu_groups'] ?? [];

        $promptLines = [];
        $promptLines[] = "=== CATÁLOGO DE MENÚS AUTORIZADO (v{$publishedCatalog->version_number}) ===";

        foreach ($menuGroups as $mg) {
            $name = $mg['name'] ?? 'Menú';
            $priceStr = ($mg['price'] > 0) ? "S/ " . number_format($mg['price'], 2) : "Consultar";
            $promptLines[] = "\n▶ {$name} ({$priceStr}):";

            $items = $mg['items'] ?? [];
            if (empty($items)) {
                $promptLines[] = "  - Sin productos individuales asignados.";
            } else {
                foreach ($items as $item) {
                    $itemName = $item['name'] ?? '';
                    $itemPriceStr = isset($item['base_price']) && $item['base_price'] > 0
                        ? " (S/ " . number_format($item['base_price'], 2) . ")"
                        : "";
                    $promptLines[] = "  - {$itemName}{$itemPriceStr}";
                }
            }
        }

        $promptLines[] = "\n=== REGLA CONSTITUCIONAL DE VENTAS ===";
        $promptLines[] = "NUNCA afirme precios o pertenencia de productos que no estén explícitamente presentes en el catálogo publicado.";

        return implode("\n", $promptLines);
    }
}
