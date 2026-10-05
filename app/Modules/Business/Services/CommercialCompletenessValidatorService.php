<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Business\Models\CatalogVersionValidation;
use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Models\ProductVisualReference;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpectedCommercialItem
{
    public string $itemKey;
    public string $commercialRole; // SELLABLE_PRODUCT, SELLABLE_VARIANT, INCLUDED_COMPONENT, INFORMATIONAL_TEXT, CATEGORY_HEADER
    public string $name;
    public string $normalizedName;
    public array $structuredQualifiers; // SERVING_SIZE, PORTION_FRACTION, WEIGHT, PIECES
    public ?float $expectedPrice;
    public bool $isRequiredForPublication;

    public function __construct(
        string $itemKey,
        string $name,
        string $commercialRole = 'SELLABLE_PRODUCT',
        ?float $expectedPrice = null,
        array $structuredQualifiers = []
    ) {
        $this->itemKey = $itemKey;
        $this->name = $name;
        $this->normalizedName = Str::of($name)->lower()->ascii()->trim()->squish()->toString();
        $this->commercialRole = $commercialRole;
        $this->expectedPrice = $expectedPrice;
        $this->structuredQualifiers = $structuredQualifiers;
        $this->isRequiredForPublication = in_array($commercialRole, ['SELLABLE_PRODUCT', 'SELLABLE_VARIANT'], true);
    }
}

class CommercialCompletenessValidatorService
{
    /**
     * Validador Formal de Completitud Comercial por Identidad (Gate AG-34 & Regla C14).
     * Exige que para todo ítem con isRequiredForPublication = true exista:
     * Evidence (raw_menu_evidences) ∧ CanonicalTarget (Product/Variant) ∧ PVR (ProductVisualReference) ∧ Status = RESOLVED.
     */
    public function validate(int $catalogVersionId, int $tenantId, ?array $customExpectedItemsByMedia = null): array
    {
        $version = CatalogVersion::where('id', $catalogVersionId)->where('tenant_id', $tenantId)->first();
        if (!$version) {
            return [
                'status' => 'INCOMPLETE',
                'reason' => 'CATALOG_VERSION_NOT_FOUND',
                'catalog_version_id' => $catalogVersionId,
                'missing_count' => 1,
                'missing_items' => [['item_key' => 'version', 'name' => 'Version ' . $catalogVersionId]],
            ];
        }

        $linkedMedia = CatalogVersionMedia::where('catalog_version_id', $catalogVersionId)
            ->where('tenant_id', $tenantId)
            ->get();

        $allExpectedItems = $customExpectedItemsByMedia ?? $this->resolveExpectedItemsForTenant($tenantId, $linkedMedia->pluck('commercial_media_id')->toArray());

        $totalExpectedSellable = 0;
        $totalEvidencesFound = 0;
        $totalCanonicalFound = 0;
        $totalPvrsFound = 0;
        $missingItems = [];

        foreach ($allExpectedItems as $mediaId => $items) {
            foreach ($items as $item) {
                if (!$item->isRequiredForPublication) {
                    continue;
                }

                $totalExpectedSellable++;
                $normName = $item->normalizedName;

                $evidence = DB::table('raw_menu_evidences')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($normName, $item) {
                        $q->whereRaw('LOWER(extracted_text) LIKE ?', ['%' . $normName . '%'])
                          ->orWhereRaw('LOWER(extracted_text) LIKE ?', ['%' . strtolower($item->name) . '%']);
                    })
                    ->first();

                if (!$evidence) {
                    $variantCheck = ProductVariant::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($normName, $item) {
                            $q->where('sku', 'LIKE', '%' . $normName . '%')
                              ->orWhere('color', 'LIKE', '%' . $normName . '%');
                        })
                        ->with('product')
                        ->first();
                    if ($variantCheck && $variantCheck->product) {
                        $pName = strtolower($variantCheck->product->name);
                        $evidence = DB::table('raw_menu_evidences')
                            ->where('tenant_id', $tenantId)
                            ->whereRaw('LOWER(extracted_text) LIKE ?', ['%' . $pName . '%'])
                            ->first();
                    }
                }

                if ($evidence) {
                    $totalEvidencesFound++;
                }

                // 2. Verificar Entidad Canónica (Product o ProductVariant)
                // Priorizar coincidencia exacta de normalized_name o name para evitar colisiones de subcadenas
                $product = Product::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($normName, $item) {
                        $q->where('normalized_name', $normName)
                          ->orWhere('name', $item->name);
                    })
                    ->first();

                if (!$product) {
                    $product = Product::where('tenant_id', $tenantId)
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . $normName . '%'])
                        ->first();
                }

                $variant = null;
                if (!$product) {
                    $variant = ProductVariant::where('tenant_id', $tenantId)
                        ->where(function ($q) use ($normName, $item) {
                            $q->where('sku', 'LIKE', '%' . $normName . '%')
                              ->orWhere('color', 'LIKE', '%' . $normName . '%');
                        })
                        ->first();
                }

                if ($product || $variant) {
                    $totalCanonicalFound++;
                }

                // 3. Verificar Referencia Visual Activa (ProductVisualReference)
                $pvr = null;
                if ($product) {
                    $pvr = ProductVisualReference::where('tenant_id', $tenantId)
                        ->where('product_id', $product->id)
                        ->where('commercial_media_id', $mediaId)
                        ->where('status', 'PUBLISHED')
                        ->first();
                } elseif ($variant) {
                    $pvr = ProductVisualReference::where('tenant_id', $tenantId)
                        ->where('variant_id', $variant->id)
                        ->where('commercial_media_id', $mediaId)
                        ->where('status', 'PUBLISHED')
                        ->first();
                }

                if ($pvr) {
                    $totalPvrsFound++;
                }

                // Invariante de Identidad AG-34
                $isFullyResolved = ($evidence !== null && ($product !== null || $variant !== null) && $pvr !== null);

                if (!$isFullyResolved) {
                    $missingItems[] = [
                        'item_key' => $item->itemKey,
                        'name' => $item->name,
                        'normalized_name' => $normName,
                        'commercial_role' => $item->commercialRole,
                        'commercial_media_id' => $mediaId,
                        'has_evidence' => ($evidence !== null),
                        'has_canonical_product' => ($product !== null || $variant !== null),
                        'has_pvr' => ($pvr !== null),
                        'failure_stage' => (!$evidence) ? 'RAW_EVIDENCE_MISSING' : ((!$product && !$variant) ? 'CANONICAL_PRODUCT_MISSING' : 'PVR_REFERENCE_MISSING'),
                    ];
                }
            }
        }

        $missingCount = count($missingItems);
        $status = ($missingCount === 0 && $totalExpectedSellable > 0) ? 'COMPLETE' : 'INCOMPLETE';

        // Persistir auditoría formal en catalog_version_validations
        CatalogVersionValidation::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'catalog_version_id' => $catalogVersionId,
            ],
            [
                'expected_sellable_count' => $totalExpectedSellable,
                'evidence_count' => $totalEvidencesFound,
                'resolved_count' => $totalCanonicalFound,
                'pvr_count' => $totalPvrsFound,
                'missing_count' => $missingCount,
                'missing_items_json' => $missingItems,
                'status' => $status,
                'validated_at' => now(),
            ]
        );

        return [
            'status' => $status,
            'catalog_version_id' => $catalogVersionId,
            'tenant_id' => $tenantId,
            'expected_sellable_count' => $totalExpectedSellable,
            'evidence_count' => $totalEvidencesFound,
            'resolved_count' => $totalCanonicalFound,
            'pvr_count' => $totalPvrsFound,
            'missing_count' => $missingCount,
            'missing_items' => $missingItems,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Resuelve el ExpectedCommercialItemSet para un conjunto de CommercialMedia de un Tenant.
     */
    public function resolveExpectedItemsForTenant(int $tenantId, array $mediaIds): array
    {
        $expected = [];

        // Si es Wallpa Brasa (Tenant 11), definir el ExpectedCommercialItemSet exhaustivo con distinción de roles
        if ($tenantId === 11) {
            foreach ($mediaIds as $mId) {
                $media = CommercialMedia::where('id', $mId)->where('tenant_id', $tenantId)->first();
                if (!$media) continue;

                $cat = strtoupper($media->category ?? '');
                $order = (int)($media->display_order ?? 1);

                if ($cat === 'CARTA_GENERAL' || $order === 1) {
                    $expected[$mId] = [
                        // Encabezados e informativos excluidos de la completitud vendible
                        new ExpectedCommercialItem('h1_pollos', 'Pollos a la Brasa', 'CATEGORY_HEADER'),
                        new ExpectedCommercialItem('info_pollos', 'Todos nuestros pollos incluyen papas andinas y ensalada', 'INFORMATIONAL_TEXT'),
                        // Productos vendibles obligatorios
                        new ExpectedCommercialItem('pollo_1', '1 Pollo a la brasa', 'SELLABLE_PRODUCT', 60.00, ['PORTION_FRACTION' => '1']),
                        new ExpectedCommercialItem('pollo_1_2', '1/2 Pollo a la brasa', 'SELLABLE_PRODUCT', 35.00, ['PORTION_FRACTION' => '1/2']),
                        new ExpectedCommercialItem('pollo_1_4', '1/4 Pollo a la brasa', 'SELLABLE_PRODUCT', 19.00, ['PORTION_FRACTION' => '1/4']),
                        new ExpectedCommercialItem('mostrito', 'Mostrito', 'SELLABLE_PRODUCT', 16.00),
                        new ExpectedCommercialItem('mostro', 'Mostro', 'SELLABLE_PRODUCT', 23.00),
                        new ExpectedCommercialItem('wallpa_aguja', 'Wallpa Aguja', 'SELLABLE_PRODUCT', 28.00),
                        new ExpectedCommercialItem('wallpa_criollo', 'Wallpa Criollo', 'SELLABLE_PRODUCT', 25.00),
                        new ExpectedCommercialItem('wallpa_parrillero', 'Wallpa Parrillero', 'SELLABLE_PRODUCT', 25.00),
                        new ExpectedCommercialItem('promo_1', 'Promo 1', 'SELLABLE_PRODUCT', 65.00),
                        new ExpectedCommercialItem('promo_2', 'Promo 2', 'SELLABLE_PRODUCT', 80.00),
                        new ExpectedCommercialItem('promo_3', 'Promo 3', 'SELLABLE_PRODUCT', 75.00),
                        new ExpectedCommercialItem('promo_4', 'Promo 4', 'SELLABLE_PRODUCT', 85.00),
                    ];
                } elseif ($cat === 'CHANCHOS' || $order === 2) {
                    $expected[$mId] = [
                        new ExpectedCommercialItem('h2_chancho', 'Chancho al Cilindro', 'CATEGORY_HEADER'),
                        new ExpectedCommercialItem('chancho_cilindro', 'Chancho al Cilindro', 'SELLABLE_PRODUCT', 28.00),
                        new ExpectedCommercialItem('chancho_250', 'Chancho al cilindro 250g', 'SELLABLE_PRODUCT', 25.00, ['WEIGHT' => '250G']),
                        new ExpectedCommercialItem('chancho_500', 'Chancho al cilindro 500g', 'SELLABLE_PRODUCT', 45.00, ['WEIGHT' => '500G']),
                        new ExpectedCommercialItem('chancho_1k', 'Chancho al cilindro 1kg', 'SELLABLE_PRODUCT', 85.00, ['WEIGHT' => '1KG']),
                        new ExpectedCommercialItem('duo_personal', 'Dúo Personal', 'SELLABLE_PRODUCT', 35.00, ['SERVING_SIZE' => 'PERSONAL']),
                        new ExpectedCommercialItem('duo_familiar', 'Dúo Familiar', 'SELLABLE_PRODUCT', 95.00, ['SERVING_SIZE' => 'FAMILIAR']),
                        new ExpectedCommercialItem('chaufa_pollo_pers', 'Chaufa de Pollo Personal', 'SELLABLE_PRODUCT', 16.00, ['SERVING_SIZE' => 'PERSONAL']),
                        new ExpectedCommercialItem('chaufa_pollo_fam', 'Chaufa de Pollo Familiar', 'SELLABLE_PRODUCT', 31.00, ['SERVING_SIZE' => 'FAMILIAR']),
                    ];
                } elseif ($cat === 'ALITAS' || $order === 3) {
                    $expected[$mId] = [
                        new ExpectedCommercialItem('h3_alitas', 'Alitas Broaster y BBQ', 'CATEGORY_HEADER'),
                        new ExpectedCommercialItem('alitas_bbq', 'Alitas BBQ', 'SELLABLE_PRODUCT', 23.00, ['PIECES' => '8_PIEZAS']),
                        new ExpectedCommercialItem('alitas_bbq_12', 'Alitas BBQ x 12', 'SELLABLE_PRODUCT', 32.00, ['PIECES' => '12_PIEZAS']),
                        new ExpectedCommercialItem('alitas_crispy', 'Alitas Crispy', 'SELLABLE_PRODUCT', 23.00, ['PIECES' => '8_PIEZAS']),
                        new ExpectedCommercialItem('salchipapa', 'Salchipapa', 'SELLABLE_PRODUCT', 18.00),
                        new ExpectedCommercialItem('lomo_saltado', 'Lomo Saltado', 'SELLABLE_PRODUCT', 23.00),
                    ];
                } elseif ($cat === 'PARRILLAS' || $order === 4) {
                    $expected[$mId] = [
                        new ExpectedCommercialItem('h4_parrillas', 'Carta Especial de Parrillas', 'CATEGORY_HEADER'),
                        new ExpectedCommercialItem('info_parrillas', 'Todas nuestras parrillas incluyen papas fritas y ensalada fresca', 'INFORMATIONAL_TEXT'),
                        // Las 3 Parrillas Físicas Fundamentales
                        new ExpectedCommercialItem('parrilla_dos', 'Parrilla para Dos', 'SELLABLE_PRODUCT', 80.00, ['SERVING_SIZE' => 'DOS']),
                        new ExpectedCommercialItem('parrilla_familiar', 'Parrilla Familiar', 'SELLABLE_PRODUCT', 150.00, ['SERVING_SIZE' => 'FAMILIAR']),
                        new ExpectedCommercialItem('parrilla_wallpa', 'Parrilla Wallpa', 'SELLABLE_PRODUCT', 180.00, ['SERVING_SIZE' => 'WALLPA']),
                        // Cortes individuales
                        new ExpectedCommercialItem('pechuga_parrilla', 'Pechuga a la parrilla', 'SELLABLE_PRODUCT', 21.00),
                        new ExpectedCommercialItem('anticuchos', 'Anticuchos', 'SELLABLE_PRODUCT', 20.00),
                        new ExpectedCommercialItem('chuleta_parrilla', 'Chuleta a la parrilla', 'SELLABLE_PRODUCT', 22.00),
                        new ExpectedCommercialItem('bistec_parrilla', 'Bistec a la parrilla', 'SELLABLE_PRODUCT', 25.00),
                        new ExpectedCommercialItem('churrasco_parrilla', 'Churrasco a la parrilla', 'SELLABLE_PRODUCT', 25.00),
                        // Pastas a la Huancaina
                        new ExpectedCommercialItem('fetuccini_lomo', 'Fetuccini a la Huancaina con Lomo', 'SELLABLE_PRODUCT', 25.00),
                        new ExpectedCommercialItem('fetuccini_pechuga', 'Fetuccini a la Huancaina con Pechuga', 'SELLABLE_PRODUCT', 22.00),
                        new ExpectedCommercialItem('fetuccini_chuleta', 'Fetuccini a la Huancaina con Chuleta', 'SELLABLE_PRODUCT', 24.00),
                        new ExpectedCommercialItem('fetuccini_bife', 'Fetuccini a la Huancaina con Bife', 'SELLABLE_PRODUCT', 26.00),
                        new ExpectedCommercialItem('fetuccini_anticucho', 'Fetuccini a la Huancaina con Anticucho', 'SELLABLE_PRODUCT', 25.00),
                        // Guarniciones
                        new ExpectedCommercialItem('papas_andinas', 'Porcion de Papas Andinas', 'SELLABLE_PRODUCT', 10.00),
                        new ExpectedCommercialItem('camote_frito', 'Porcion de Camote Frito', 'SELLABLE_PRODUCT', 8.00),
                        new ExpectedCommercialItem('salchicha_frankfurter', 'Porcion de Salchicha Frankfurter', 'SELLABLE_PRODUCT', 8.00),
                        new ExpectedCommercialItem('chorizo', 'Porcion de Chorizo', 'SELLABLE_PRODUCT', 8.00),
                        new ExpectedCommercialItem('platano_frito', 'Porcion de Platano Frito', 'SELLABLE_PRODUCT', 5.00),
                        new ExpectedCommercialItem('huevo_frito', 'Huevo Frito', 'SELLABLE_PRODUCT', 2.50),
                        new ExpectedCommercialItem('arroz_blanco', 'Porcion de Arroz Blanco', 'SELLABLE_PRODUCT', 5.00),
                        new ExpectedCommercialItem('arroz_chaufa', 'Porcion de Arroz Chaufa', 'SELLABLE_PRODUCT', 8.00),
                        new ExpectedCommercialItem('ensalada_fresca', 'Ensalada Fresca', 'SELLABLE_PRODUCT', 8.00),
                        new ExpectedCommercialItem('ensalada_cocida', 'Ensalada Cocida', 'SELLABLE_PRODUCT', 10.00),
                    ];
                }
            }
        } else {
            foreach ($mediaIds as $mId) {
                $pvrs = ProductVisualReference::where('tenant_id', $tenantId)->where('commercial_media_id', $mId)->get();
                $expected[$mId] = [];
                foreach ($pvrs as $p) {
                    $name = $p->product ? $p->product->name : ($p->variant ? $p->variant->sku : 'Item ' . $p->id);
                    $expected[$mId][] = new ExpectedCommercialItem('auto_' . $p->id, $name, 'SELLABLE_PRODUCT');
                }
            }
        }

        return $expected;
    }
}
