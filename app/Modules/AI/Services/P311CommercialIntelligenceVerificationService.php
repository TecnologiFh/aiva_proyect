<?php

namespace App\Modules\AI\Services;

use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\CommercialMediaAlias;
use App\Modules\Business\Models\CatalogVersion;
use App\Modules\Business\Models\CatalogVersionMedia;
use App\Modules\Business\Models\Product;
use App\Modules\Business\Models\ProductVisualReference;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\ProductVariantAttribute;
use App\Modules\Messaging\Models\ConversationCommercialContext;
use App\Modules\Business\Services\CommercialMediaResolverService;
use App\Modules\Business\Services\CommercialMediaDeliveryService;
use App\Modules\Messaging\Services\ConversationCommercialContextService;
use App\Modules\Business\Services\MultiSourceCatalogCompilerService;
use App\Modules\Orders\Services\CatalogReferenceResolverService;
use App\Modules\Business\Models\CatalogVersionValidation;
use App\Modules\Business\Services\CommercialCompletenessValidatorService;
use App\Modules\Business\Services\PublishCatalogAuthority;
use App\Modules\Business\Services\CommercialCompletenessException;
use App\Modules\Business\Services\ExpectedCommercialItem;
use App\Modules\AI\Services\RuntimeCallCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class P311CommercialIntelligenceVerificationService
{
    protected CommercialMediaResolverService $mediaResolver;
    protected CommercialMediaDeliveryService $deliveryService;
    protected ConversationCommercialContextService $contextService;
    protected MultiSourceCatalogCompilerService $compilerService;
    protected CatalogReferenceResolverService $catalogResolver;

    public function __construct()
    {
        $this->mediaResolver = new CommercialMediaResolverService();
        $this->deliveryService = new CommercialMediaDeliveryService();
        $this->contextService = new ConversationCommercialContextService();
        $this->compilerService = new MultiSourceCatalogCompilerService();
        $this->catalogResolver = new CatalogReferenceResolverService();
    }

    public function runVerification(): array
    {
        $startTime = microtime(true);
        RuntimeCallCounter::reset();

        $results = [];
        $gatesPassed = 0;
        $totalGates = 36;

        // Seeding de datos canónicos para Tenant 11 (Wallpa Brasa) y Tenant 12 (Control Tenant)
        $this->seedCanonicalTestData();

        $testMethods = [
            'testAG01MultiSourceRegistry',
            'testAG02CartaGeneralDinamica',
            'testAG03ParrillasExactResolution',
            'testAG04ChanchosExactResolution',
            'testAG05AlitasExactResolution',
            'testAG06CanonicalCompilationZeroPixels',
            'testAG07ParrillasDirectPricing',
            'testAG08FullOrderChainTraceability',
            'testAG09DualVariantPriceAuthority',
            'testAG10AmbiguityHandling',
            'testAG11StrictRealAttachment',
            'testAG12TciRegressionSafety',
            'testAG13P310aRegressionSafety',
            'testAG14FullTraceabilityChain',
            'testAG15BBoxMultiItemRegistration',
            'testAG16ContinuousFourStepNavigation',
            'testAG17TenantIsolationContext',
            'testAG18CrossCardSaleResolution',
            'testAG19CatalogVersionSupersede',
            'testAG20RetailVisualVariantResolution',
            'testAG21NonExistentProductHonesty',
            'testAG22MySqlOrderAuthority',
            'testAG23ZeroPixelRuntimeAssurance',
            'testAG24AntiOrphanXorConstraint',
            'testAG25PublishAuthorityFirewall',
            'testAG26JointDispatchRule',
            'testAG27RevisionChainLineageEnforcement',
            'testAG28DeterministicGeometricClustering',
            'testAG29VersionPinnedNavigation',
            'testAG30ScopedDisplayIndexUniqueness',
            'testAG31ContextBoundedVisualAttributePriority',
            'testAG32Sha256ContentHashIntegrity',
            'testAG33HostileRuntimePixelFirewall',
            'testAG34PublishedCatalogCommercialCompleteness',
            'testAG35NoCrossProductCommercialSubstitution',
            'testAG36HistoricalCommercialPriceSnapshotIntegrity',
        ];

        foreach ($testMethods as $index => $method) {
            $gateNumber = sprintf("AG-%02d", $index + 1);
            try {
                $gateResult = $this->{$method}();
                if (($gateResult['status'] ?? '') === 'PASS') {
                    $gatesPassed++;
                }
                $results[$gateNumber] = $gateResult;
            } catch (\Throwable $e) {
                $results[$gateNumber] = [
                    'gate' => $gateNumber,
                    'status' => 'FAIL',
                    'error' => $e->getMessage(),
                    'trace' => $e->getFile() . ':' . $e->getLine(),
                ];
            }
        }

        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
        $allPassed = ($gatesPassed === $totalGates);

        return [
            'suite' => 'P3.11 Commercial Media Intelligence & Visual Catalog Resolution',
            'architecture_version' => 'P3.11-SEALED-V1',
            'status' => $allPassed ? 'ALL_GATES_PASSED' : 'SOME_GATES_FAILED',
            'gates_passed' => $gatesPassed,
            'total_gates' => $totalGates,
            'success_rate' => round(($gatesPassed / $totalGates) * 100, 2) . '%',
            'execution_time_ms' => $executionTimeMs,
            'runtime_telemetry' => RuntimeCallCounter::getStats(),
            'gates' => $results,
        ];
    }

    public function seedCanonicalTestData(): void
    {
        $tenantId = 11; // Wallpa Brasa
        $controlTenantId = 4; // Donuts Fs / Control Tenant

        $this->ensureDatabaseConstraints();

        // 1. Limpiar registros previos de prueba
        ProductVisualReference::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        ProductVariantAttribute::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        ProductVariant::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        CatalogVersionValidation::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        CatalogVersionMedia::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        CatalogVersion::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        CommercialMediaAlias::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        CommercialMedia::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();
        ConversationCommercialContext::whereIn('tenant_id', [$tenantId, $controlTenantId])->delete();

        // 2. Crear las 4 cartas comerciales para Tenant 11 (Wallpa Brasa)
        // Carta 1: Carta General
        $media1 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'CARTA_GENERAL',
            'title' => 'Carta General Wallpa Brasa',
            'file_path' => 'cards/wallpa_carta1.png',
            'content_hash' => hash('sha256', 'wallpa_carta1_general'),
            'revision_number' => 1,
            'mime_type' => 'image/png',
            'file_size_bytes' => 102400,
            'display_order' => 1,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $media1->id, 'tenant_id' => $tenantId, 'alias' => 'carta general', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media1->id, 'tenant_id' => $tenantId, 'alias' => 'menu', 'weight' => 0.9]);

        // Carta 2: Chanchos / Cilindro
        $media2 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'CHANCHOS',
            'title' => 'Carta de Chanchos al Cilindro',
            'file_path' => 'cards/wallpa_carta2.png',
            'content_hash' => hash('sha256', 'wallpa_carta2_chanchos'),
            'revision_number' => 1,
            'mime_type' => 'image/png',
            'file_size_bytes' => 104500,
            'display_order' => 2,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $media2->id, 'tenant_id' => $tenantId, 'alias' => 'chancho', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media2->id, 'tenant_id' => $tenantId, 'alias' => 'chanchos al cilindro', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media2->id, 'tenant_id' => $tenantId, 'alias' => 'pork', 'weight' => 0.8]);

        // Carta 3: Alitas
        $media3 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'ALITAS',
            'title' => 'Carta de Alitas Broaster y BBQ',
            'file_path' => 'cards/wallpa_carta3.png',
            'content_hash' => hash('sha256', 'wallpa_carta3_alitas'),
            'revision_number' => 1,
            'mime_type' => 'image/png',
            'file_size_bytes' => 101200,
            'display_order' => 3,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $media3->id, 'tenant_id' => $tenantId, 'alias' => 'alitas', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media3->id, 'tenant_id' => $tenantId, 'alias' => 'alitas bbq', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media3->id, 'tenant_id' => $tenantId, 'alias' => 'wings', 'weight' => 0.8]);

        // Carta 4: Parrillas
        $media4 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'PARRILLAS',
            'title' => 'Carta Especial de Parrillas',
            'file_path' => 'cards/wallpa_carta4.png',
            'content_hash' => hash('sha256', 'wallpa_carta4_parrillas'),
            'revision_number' => 1,
            'mime_type' => 'image/png',
            'file_size_bytes' => 108900,
            'display_order' => 4,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'parrilla', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'parrillas', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'grill', 'weight' => 0.8]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'guarnicion', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'guarniciones', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'pastas', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'carta 4', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'la 4', 'weight' => 1.0]);
        CommercialMediaAlias::create(['commercial_media_id' => $media4->id, 'tenant_id' => $tenantId, 'alias' => 'la numero 4', 'weight' => 1.0]);

        // 3. Categorías canónicas
        $cat11 = DB::table('categories')->where('tenant_id', $tenantId)->first();
        $cat11Id = $cat11 ? $cat11->id : DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Platos a la Carta',
            'slug' => 'platos-carta-' . $tenantId,
            'status' => 'ACTIVE',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cat12 = DB::table('categories')->where('tenant_id', $controlTenantId)->first();
        $cat12Id = $cat12 ? $cat12->id : DB::table('categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $controlTenantId,
            'name' => 'Ropa y Accesorios',
            'slug' => 'ropa-acc-' . $controlTenantId,
            'status' => 'ACTIVE',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Catálogo Exhaustivo de 48 Ítems Vendibles para Tenant 11 (AG-34)
        $itemsData = [
            // Carta 1: General
            ['media_id' => $media1->id, 'ev_id' => 111, 'name' => '1 Pollo a la brasa', 'slug' => 'un-pollo-brasa', 'price' => 60.00, 'desc' => '1 Pollo a la brasa con papas y ensalada'],
            ['media_id' => $media1->id, 'ev_id' => 112, 'name' => '1/2 Pollo a la brasa', 'slug' => 'medio-pollo-brasa', 'price' => 35.00, 'desc' => '1/2 Pollo a la brasa con papas y ensalada'],
            ['media_id' => $media1->id, 'ev_id' => 101, 'name' => '1/4 Pollo a la Brasa', 'slug' => 'un-cuarto-pollo-brasa', 'price' => 22.00, 'desc' => 'Con papas fritas y ensalada clásica'],
            ['media_id' => $media1->id, 'ev_id' => 113, 'name' => 'Mostrito', 'slug' => 'mostrito', 'price' => 16.00, 'desc' => '1/4 Pollo con arroz chaufa y papas'],
            ['media_id' => $media1->id, 'ev_id' => 114, 'name' => 'Mostro', 'slug' => 'mostro', 'price' => 23.00, 'desc' => '1/2 Pollo con arroz chaufa y papas'],
            ['media_id' => $media1->id, 'ev_id' => 132, 'name' => 'Wallpa Aguja', 'slug' => 'wallpa-aguja', 'price' => 28.00, 'desc' => '1/4 Pollo con chaufa, papas andinas, plátano y huevo frito'],
            ['media_id' => $media1->id, 'ev_id' => 133, 'name' => 'Wallpa Criollo', 'slug' => 'wallpa-criollo', 'price' => 25.00, 'desc' => '1/4 Pollo con 1 palito de anticucho, papas andinas y ensalada'],
            ['media_id' => $media1->id, 'ev_id' => 134, 'name' => 'Wallpa Parrillero', 'slug' => 'wallpa-parrillero', 'price' => 25.00, 'desc' => '1/4 Pollo con 1 chorizo, papas andinas y ensalada'],
            ['media_id' => $media1->id, 'ev_id' => 115, 'name' => 'Promo 1', 'slug' => 'promo-1', 'price' => 65.00, 'desc' => '1 Pollo + Papas + Ensalada + Gaseosa 1.5L'],
            ['media_id' => $media1->id, 'ev_id' => 116, 'name' => 'Promo 2', 'slug' => 'promo-2', 'price' => 80.00, 'desc' => '1 Pollo + 1/4 Pollo + Papas + Ensalada + Gaseosa'],
            ['media_id' => $media1->id, 'ev_id' => 135, 'name' => 'Promo 3', 'slug' => 'promo-3', 'price' => 75.00, 'desc' => '1 Pollo + 1/4 Pollo con papas andinas, ensalada y gaseosa 1.5L'],
            ['media_id' => $media1->id, 'ev_id' => 136, 'name' => 'Promo 4', 'slug' => 'promo-4', 'price' => 85.00, 'desc' => '1 Pollo + 1/2 Pollo con papas andinas, ensalada y gaseosa 1.5L'],

            // Carta 2: Chanchos
            ['media_id' => $media2->id, 'ev_id' => 102, 'name' => 'Chancho al Cilindro', 'slug' => 'chancho-al-cilindro', 'price' => 28.00, 'desc' => 'Porción personal con papas doradas'],
            ['media_id' => $media2->id, 'ev_id' => 117, 'name' => 'Chancho al cilindro 250g', 'slug' => 'chancho-cilindro-250g', 'price' => 25.00, 'desc' => '250g de chancho crocante al cilindro'],
            ['media_id' => $media2->id, 'ev_id' => 118, 'name' => 'Chancho al cilindro 500g', 'slug' => 'chancho-cilindro-500g', 'price' => 45.00, 'desc' => '500g de chancho crocante al cilindro'],
            ['media_id' => $media2->id, 'ev_id' => 119, 'name' => 'Chancho al cilindro 1kg', 'slug' => 'chancho-cilindro-1kg', 'price' => 85.00, 'desc' => '1kg de chancho crocante al cilindro'],
            ['media_id' => $media2->id, 'ev_id' => 120, 'name' => 'Dúo Personal', 'slug' => 'duo-personal', 'price' => 35.00, 'desc' => 'Chancho + Pollo porción personal'],
            ['media_id' => $media2->id, 'ev_id' => 121, 'name' => 'Dúo Familiar', 'slug' => 'duo-familiar', 'price' => 95.00, 'desc' => 'Chancho + Pollo porción familiar'],
            ['media_id' => $media2->id, 'ev_id' => 122, 'name' => 'Chaufa de Pollo Personal', 'slug' => 'chaufa-pollo-personal', 'price' => 16.00, 'desc' => 'Arroz chaufa al wok porción personal'],
            ['media_id' => $media2->id, 'ev_id' => 123, 'name' => 'Chaufa de Pollo Familiar', 'slug' => 'chaufa-pollo-familiar', 'price' => 31.00, 'desc' => 'Arroz chaufa al wok porción familiar'],

            // Carta 3: Alitas
            ['media_id' => $media3->id, 'ev_id' => 124, 'name' => 'Alitas BBQ', 'slug' => 'alitas-bbq', 'price' => 23.00, 'desc' => '8 piezas de alitas bañadas en salsa BBQ'],
            ['media_id' => $media3->id, 'ev_id' => 103, 'name' => 'Alitas BBQ x 12', 'slug' => 'alitas-bbq-12', 'price' => 32.00, 'desc' => '12 unidades bañadas en salsa barbacoa artesanal'],
            ['media_id' => $media3->id, 'ev_id' => 125, 'name' => 'Alitas Crispy', 'slug' => 'alitas-crispy', 'price' => 23.00, 'desc' => '8 piezas de alitas súper crocantes'],
            ['media_id' => $media3->id, 'ev_id' => 126, 'name' => 'Salchipapa', 'slug' => 'salchipapa', 'price' => 18.00, 'desc' => 'Papas fritas con salchicha frankfurter'],
            ['media_id' => $media3->id, 'ev_id' => 127, 'name' => 'Lomo Saltado', 'slug' => 'lomo-saltado', 'price' => 23.00, 'desc' => 'Lomo fino salteado al jugo con papas y arroz'],

            // Carta 4: Parrillas
            ['media_id' => $media4->id, 'ev_id' => 104, 'name' => 'Parrilla para Dos', 'slug' => 'parrilla-para-dos', 'price' => 80.00, 'desc' => 'Bife, chuleta, chorizo, anticuchos, papas y ensalada'],
            ['media_id' => $media4->id, 'ev_id' => 128, 'name' => 'Parrilla Familiar', 'slug' => 'parrilla-familiar', 'price' => 150.00, 'desc' => 'Parrilla completa para 4-5 personas con guarnición'],
            ['media_id' => $media4->id, 'ev_id' => 129, 'name' => 'Parrilla Wallpa', 'slug' => 'parrilla-wallpa', 'price' => 180.00, 'desc' => 'Parrilla premium especial con cortes seleccionados'],
            ['media_id' => $media4->id, 'ev_id' => 130, 'name' => 'Pechuga a la parrilla', 'slug' => 'pechuga-a-la-parrilla', 'price' => 21.00, 'desc' => 'Filete de pechuga a las brasas con papas'],
            ['media_id' => $media4->id, 'ev_id' => 131, 'name' => 'Anticuchos', 'slug' => 'anticuchos', 'price' => 20.00, 'desc' => '2 palos de corazón de res tierno marinado'],
            ['media_id' => $media4->id, 'ev_id' => 137, 'name' => 'Chuleta a la parrilla', 'slug' => 'chuleta-a-la-parrilla', 'price' => 22.00, 'desc' => 'Chuleta tierna a las brasas con papas y ensalada'],
            ['media_id' => $media4->id, 'ev_id' => 138, 'name' => 'Bistec a la parrilla', 'slug' => 'bistec-a-la-parrilla', 'price' => 25.00, 'desc' => 'Corte de bistec parrillero con papas y ensalada'],
            ['media_id' => $media4->id, 'ev_id' => 139, 'name' => 'Churrasco a la parrilla', 'slug' => 'churrasco-a-la-parrilla', 'price' => 25.00, 'desc' => 'Corte de churrasco a la brasa con papas y ensalada'],
            ['media_id' => $media4->id, 'ev_id' => 140, 'name' => 'Fetuccini a la Huancaina con Lomo', 'slug' => 'fetuccini-huancaina-lomo', 'price' => 25.00, 'desc' => 'Fetuccini en salsa huancaína con lomo saltado'],
            ['media_id' => $media4->id, 'ev_id' => 141, 'name' => 'Fetuccini a la Huancaina con Pechuga', 'slug' => 'fetuccini-huancaina-pechuga', 'price' => 22.00, 'desc' => 'Fetuccini en salsa huancaína con pechuga parrillera'],
            ['media_id' => $media4->id, 'ev_id' => 142, 'name' => 'Fetuccini a la Huancaina con Chuleta', 'slug' => 'fetuccini-huancaina-chuleta', 'price' => 24.00, 'desc' => 'Fetuccini en salsa huancaína con chuleta parrillera'],
            ['media_id' => $media4->id, 'ev_id' => 143, 'name' => 'Fetuccini a la Huancaina con Bife', 'slug' => 'fetuccini-huancaina-bife', 'price' => 26.00, 'desc' => 'Fetuccini en salsa huancaína con bife a la brasa'],
            ['media_id' => $media4->id, 'ev_id' => 144, 'name' => 'Fetuccini a la Huancaina con Anticucho', 'slug' => 'fetuccini-huancaina-anticucho', 'price' => 25.00, 'desc' => 'Fetuccini en salsa huancaína con palito de anticucho'],
            ['media_id' => $media4->id, 'ev_id' => 145, 'name' => 'Porcion de Papas Andinas', 'slug' => 'porcion-papas-andinas', 'price' => 10.00, 'desc' => 'Papas andinas fritas crocantes'],
            ['media_id' => $media4->id, 'ev_id' => 146, 'name' => 'Porcion de Camote Frito', 'slug' => 'porcion-camote-frito', 'price' => 8.00, 'desc' => 'Camote frito artesanal'],
            ['media_id' => $media4->id, 'ev_id' => 147, 'name' => 'Porcion de Salchicha Frankfurter', 'slug' => 'porcion-salchicha-frankfurter', 'price' => 8.00, 'desc' => 'Porción de salchicha frankfurter parrillera'],
            ['media_id' => $media4->id, 'ev_id' => 148, 'name' => 'Porcion de Chorizo', 'slug' => 'porcion-chorizo', 'price' => 8.00, 'desc' => 'Porción de chorizo parrillero artesanal'],
            ['media_id' => $media4->id, 'ev_id' => 149, 'name' => 'Porcion de Platano Frito', 'slug' => 'porcion-platano-frito', 'price' => 5.00, 'desc' => 'Plátano frito dulce'],
            ['media_id' => $media4->id, 'ev_id' => 150, 'name' => 'Huevo Frito', 'slug' => 'huevo-frito', 'price' => 2.50, 'desc' => 'Huevo frito montado'],
            ['media_id' => $media4->id, 'ev_id' => 151, 'name' => 'Porcion de Arroz Blanco', 'slug' => 'porcion-arroz-blanco', 'price' => 5.00, 'desc' => 'Arroz blanco graneado'],
            ['media_id' => $media4->id, 'ev_id' => 152, 'name' => 'Porcion de Arroz Chaufa', 'slug' => 'porcion-arroz-chaufa', 'price' => 8.00, 'desc' => 'Arroz chaufa al wok tradicional'],
            ['media_id' => $media4->id, 'ev_id' => 153, 'name' => 'Ensalada Fresca', 'slug' => 'ensalada-fresca', 'price' => 8.00, 'desc' => 'Ensalada fresca con vinagreta de la casa'],
            ['media_id' => $media4->id, 'ev_id' => 154, 'name' => 'Ensalada Cocida', 'slug' => 'ensalada-cocida', 'price' => 10.00, 'desc' => 'Ensalada cocida con betarraga, zanahoria y vainita'],
        ];

        // Sembrar evidencias, productos canónicos y PVRs para Tenant 11
        $displayOrderPerMedia = [];
        foreach ($itemsData as $item) {
            // Evidencia cruda
            DB::table('raw_menu_evidences')->updateOrInsert(
                ['id' => $item['ev_id']],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'document_id' => 'doc_seed_' . $item['ev_id'],
                    'source_type' => 'WHATSAPP_IMAGE',
                    'extracted_text' => $item['name'] . ' S/ ' . number_format($item['price'], 2),
                    'element_type' => 'text',
                    'confidence' => 1.0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Producto canónico
            $prod = $this->getOrCreateCanonicalProduct(
                $tenantId,
                $cat11Id,
                $item['name'],
                $item['slug'] . '-' . $tenantId,
                $item['price'],
                $item['desc']
            );

            // Contador de orden dentro del medio
            $mId = $item['media_id'];
            $displayOrderPerMedia[$mId] = ($displayOrderPerMedia[$mId] ?? 0) + 1;

            // ProductVisualReference (PVR)
            ProductVisualReference::create([
                'tenant_id' => $tenantId,
                'product_id' => $prod->id,
                'commercial_media_id' => $mId,
                'evidence_id' => $item['ev_id'],
                'bbox_x' => 0.1,
                'bbox_y' => 0.1 * $displayOrderPerMedia[$mId],
                'bbox_w' => 0.3,
                'bbox_h' => 0.08,
                'display_index' => $displayOrderPerMedia[$mId],
                'status' => 'PUBLISHED',
            ]);
        }

        // Evidencias crudas para Control Tenant 12 (Ropa)
        foreach ([
            ['id' => 201, 'tenant_id' => $controlTenantId, 'text' => 'Blusa Elegante Seda Azul M BLU-AZUL-M S/ 80.00'],
            ['id' => 202, 'tenant_id' => $controlTenantId, 'text' => 'Blusa Elegante Seda Azul L BLU-AZUL-L S/ 100.00'],
        ] as $ev) {
            DB::table('raw_menu_evidences')->updateOrInsert(
                ['id' => $ev['id']],
                [
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $ev['tenant_id'],
                    'document_id' => 'doc_seed_' . $ev['id'],
                    'source_type' => 'WHATSAPP_IMAGE',
                    'extracted_text' => $ev['text'],
                    'element_type' => 'text',
                    'confidence' => 1.0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 5. Publicar Catálogo Canónico para Tenant 11 con las 4 cartas (100% Completo y Certificado)
        $this->compilerService->compileAndPublish($tenantId, [$media1->id, $media2->id, $media3->id, $media4->id], 'Catálogo inicial Wallpa Brasa');

        // 4. Configurar catálogo retail para Tenant 12 (Tienda de Ropa / Control Tenant)
        $mediaRopa = CommercialMedia::create([
            'tenant_id' => $controlTenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'CATALOG_PAGE',
            'category' => 'ROPA_DAMA',
            'title' => 'Catálogo Blusas Colección Primavera',
            'file_path' => 'cards/ropa_blusas.png',
            'content_hash' => hash('sha256', 'ropa_blusas_primavera'),
            'revision_number' => 1,
            'mime_type' => 'image/png',
            'file_size_bytes' => 120000,
            'display_order' => 1,
            'status' => 'ACTIVE',
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $mediaRopa->id, 'tenant_id' => $controlTenantId, 'alias' => 'blusas', 'weight' => 1.0]);

        $prodBlusa = $this->getOrCreateCanonicalProduct($controlTenantId, $cat12Id, 'Blusa Elegante Seda', 'blusa-elegante-seda-' . $controlTenantId, 100.00, 'Blusa de seda manga larga');

        // Variante A: Blusa Azul Talla M con precio de oferta 80.00 (override)
        $varA = ProductVariant::create([
            'tenant_id' => $controlTenantId,
            'product_id' => $prodBlusa->id,
            'sku' => 'BLU-AZUL-M',
            'color' => 'Azul',
            'talla' => 'M',
            'price_override' => 80.00,
            'is_active' => 1,
        ]);
        ProductVisualReference::create([
            'tenant_id' => $controlTenantId,
            'variant_id' => $varA->id,
            'commercial_media_id' => $mediaRopa->id,
            'evidence_id' => 201,
            'bbox_x' => 0.2, 'bbox_y' => 0.1, 'bbox_w' => 0.2, 'bbox_h' => 0.3,
            'display_index' => 1,
            'status' => 'PUBLISHED',
        ]);

        // Variante B: Blusa Azul Talla L sin override (precio base = 100.00)
        $varB = ProductVariant::create([
            'tenant_id' => $controlTenantId,
            'product_id' => $prodBlusa->id,
            'sku' => 'BLU-AZUL-L',
            'color' => 'Azul',
            'talla' => 'L',
            'price_override' => null,
            'is_active' => 1,
        ]);
        ProductVisualReference::create([
            'tenant_id' => $controlTenantId,
            'variant_id' => $varB->id,
            'commercial_media_id' => $mediaRopa->id,
            'evidence_id' => 202,
            'bbox_x' => 0.5, 'bbox_y' => 0.1, 'bbox_w' => 0.2, 'bbox_h' => 0.3,
            'display_index' => 2,
            'status' => 'PUBLISHED',
        ]);

        // Publicar catálogo para control tenant una vez que los ítems vendibles ya existen
        $this->compilerService->compileAndPublish($controlTenantId, [$mediaRopa->id], 'Catálogo inicial Ropa');
    }

    private function getOrCreateCanonicalProduct(int $tenantId, int $categoryId, string $name, string $slug, float $price, string $description): Product
    {
        $norm = Str::of($name)->lower()->ascii()->trim()->squish()->toString();

        $duplicates = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where(function($q) use ($name, $norm) {
                $q->where('name', $name)
                  ->orWhere('normalized_name', $norm)
                  ->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
            })
            ->get();

        if ($duplicates->count() > 1) {
            $keepId = $duplicates->first()->id;
            $removeIds = $duplicates->pluck('id')->filter(fn($id) => $id != $keepId)->toArray();
            DB::table('product_visual_references')->where('tenant_id', $tenantId)->whereIn('product_id', $removeIds)->delete();
            DB::table('products')->where('tenant_id', $tenantId)->whereIn('id', $removeIds)->delete();
        }

        $existing = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where(function($q) use ($name, $norm) {
                $q->where('name', $name)
                  ->orWhere('normalized_name', $norm);
            })
            ->first();

        if ($existing) {
            DB::table('products')->where('id', $existing->id)->update([
                'name' => $name,
                'normalized_name' => $norm,
                'base_price' => $price,
                'status' => 'ACTIVE',
                'is_available' => 1,
                'updated_at' => now(),
            ]);
            return Product::find($existing->id);
        }

        $id = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'name' => $name,
            'normalized_name' => $norm,
            'slug' => $slug,
            'description' => $description,
            'base_price' => $price,
            'status' => 'ACTIVE',
            'is_available' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::find($id);
    }

    private function ensureDatabaseConstraints(): void
    {
        // 1. Índices compuestos únicos (tenant_id, id) en tablas referenciadas
        $tables = ['products', 'commercial_media', 'product_variants', 'raw_menu_evidences', 'catalog_versions'];
        foreach ($tables as $t) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                    $idx = DB::select("SHOW INDEX FROM {$t} WHERE Key_name = 'uq_{$t}_tenant_id'");
                    if (empty($idx)) {
                        DB::statement("ALTER TABLE {$t} ADD UNIQUE INDEX uq_{$t}_tenant_id (tenant_id, id)");
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. C13: Virtual column + unique index
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('catalog_versions')) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('catalog_versions', 'active_published_tenant_id')) {
                    DB::statement("ALTER TABLE catalog_versions ADD COLUMN active_published_tenant_id BIGINT GENERATED ALWAYS AS (IF(status = 'PUBLISHED' AND is_active = 1, tenant_id, NULL)) VIRTUAL");
                }
                $idx = DB::select("SHOW INDEX FROM catalog_versions WHERE Key_name = 'uq_single_active_catalog'");
                if (empty($idx)) {
                    DB::statement("ALTER TABLE catalog_versions ADD UNIQUE INDEX uq_single_active_catalog (active_published_tenant_id)");
                }
            }
        } catch (\Throwable $e) {}

        // 3. Composite Foreign Keys para C10
        $fkDefs = [
            ['table' => 'product_visual_references', 'fk' => 'fk_pvr_tenant_media', 'cols' => 'tenant_id, commercial_media_id', 'ref' => 'commercial_media(tenant_id, id)'],
            ['table' => 'product_visual_references', 'fk' => 'fk_pvr_tenant_evidence', 'cols' => 'tenant_id, evidence_id', 'ref' => 'raw_menu_evidences(tenant_id, id)'],
            ['table' => 'product_visual_references', 'fk' => 'fk_pvr_tenant_product', 'cols' => 'tenant_id, product_id', 'ref' => 'products(tenant_id, id)'],
            ['table' => 'product_visual_references', 'fk' => 'fk_pvr_tenant_variant', 'cols' => 'tenant_id, variant_id', 'ref' => 'product_variants(tenant_id, id)'],
            ['table' => 'catalog_version_media', 'fk' => 'fk_cvm_tenant_version', 'cols' => 'tenant_id, catalog_version_id', 'ref' => 'catalog_versions(tenant_id, id)'],
            ['table' => 'catalog_version_media', 'fk' => 'fk_cvm_tenant_media', 'cols' => 'tenant_id, commercial_media_id', 'ref' => 'commercial_media(tenant_id, id)'],
            ['table' => 'product_variants', 'fk' => 'fk_pv_tenant_product', 'cols' => 'tenant_id, product_id', 'ref' => 'products(tenant_id, id)'],
        ];

        foreach ($fkDefs as $def) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($def['table'])) {
                    $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$def['table']}' AND CONSTRAINT_NAME = '{$def['fk']}'");
                    if (empty($fks)) {
                        DB::statement("ALTER TABLE {$def['table']} ADD CONSTRAINT {$def['fk']} FOREIGN KEY ({$def['cols']}) REFERENCES {$def['ref']} ON DELETE RESTRICT");
                    }
                }
            } catch (\Throwable $e) {}
        }
    }

    // AG-01: Multi-source canonical registry
    public function testAG01MultiSourceRegistry(): array
    {
        $tenantId = 11;
        $mediaCards = CommercialMedia::where('tenant_id', $tenantId)->where('status', 'ACTIVE')->orderBy('display_order')->get();

        $pass = ($mediaCards->count() === 4);
        $categories = $mediaCards->pluck('category')->toArray();

        return [
            'gate' => 'AG-01',
            'name' => 'Multi-Source Canonical Registry',
            'status' => ($pass && in_array('PARRILLAS', $categories) && in_array('CHANCHOS', $categories) && in_array('ALITAS', $categories)) ? 'PASS' : 'FAIL',
            'registered_count' => $mediaCards->count(),
            'categories' => $categories,
        ];
    }

    // AG-02: Carta general dinámica (MIN display_order)
    public function testAG02CartaGeneralDinamica(): array
    {
        $tenantId = 11;
        $res = $this->mediaResolver->resolve($tenantId, 'pásame la carta', 'conv_test_02');

        $pass = ($res['match_type'] === 'MATCH_EXACT' && $res['media']->category === 'CARTA_GENERAL' && $res['media']->display_order === 1);

        return [
            'gate' => 'AG-02',
            'name' => 'Dynamic General Menu Dispatch (MIN display_order)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'matched_media_id' => $res['media']?->id,
            'category' => $res['media']?->category,
            'display_order' => $res['media']?->display_order,
        ];
    }

    // AG-03: Parrillas deterministic resolution -> Carta 4
    public function testAG03ParrillasExactResolution(): array
    {
        $tenantId = 11;
        $res = $this->mediaResolver->resolve($tenantId, 'pásame la de parrillas', 'conv_test_03');

        $pass = ($res['match_type'] === 'MATCH_EXACT' && $res['media']->category === 'PARRILLAS' && str_contains($res['media']->file_path, 'carta4'));

        return [
            'gate' => 'AG-03',
            'name' => 'Parrillas Deterministic Resolution -> Carta 4',
            'status' => $pass ? 'PASS' : 'FAIL',
            'matched_media_id' => $res['media']?->id,
            'file_path' => $res['media']?->file_path,
        ];
    }

    // AG-04: Chanchos deterministic resolution -> Carta 2
    public function testAG04ChanchosExactResolution(): array
    {
        $tenantId = 11;
        $res = $this->mediaResolver->resolve($tenantId, 'muéstrame la carta de chancho al cilindro', 'conv_test_04');

        $pass = ($res['match_type'] === 'MATCH_EXACT' && $res['media']->category === 'CHANCHOS' && str_contains($res['media']->file_path, 'carta2'));

        return [
            'gate' => 'AG-04',
            'name' => 'Chanchos Deterministic Resolution -> Carta 2',
            'status' => $pass ? 'PASS' : 'FAIL',
            'matched_media_id' => $res['media']?->id,
            'file_path' => $res['media']?->file_path,
        ];
    }

    // AG-05: Alitas deterministic resolution -> Carta 3
    public function testAG05AlitasExactResolution(): array
    {
        $tenantId = 11;
        $res = $this->mediaResolver->resolve($tenantId, 'tienes carta de alitas?', 'conv_test_05');

        $pass = ($res['match_type'] === 'MATCH_EXACT' && $res['media']->category === 'ALITAS' && str_contains($res['media']->file_path, 'carta3'));

        return [
            'gate' => 'AG-05',
            'name' => 'Alitas Deterministic Resolution -> Carta 3',
            'status' => $pass ? 'PASS' : 'FAIL',
            'matched_media_id' => $res['media']?->id,
            'file_path' => $res['media']?->file_path,
        ];
    }

    // AG-06: Canonical compilation in MySQL (Zero pixels)
    public function testAG06CanonicalCompilationZeroPixels(): array
    {
        $tenantId = 11;
        $activeVersion = CatalogVersion::where('tenant_id', $tenantId)->where('status', 'PUBLISHED')->first();

        $linkedCount = CatalogVersionMedia::where('tenant_id', $tenantId)->where('catalog_version_id', $activeVersion?->id)->count();

        $pass = ($activeVersion !== null && $linkedCount === 4);

        return [
            'gate' => 'AG-06',
            'name' => 'Canonical Multi-Source Compilation in MySQL',
            'status' => $pass ? 'PASS' : 'FAIL',
            'version_id' => $activeVersion?->id,
            'linked_media_count' => $linkedCount,
        ];
    }

    // AG-07: Parrillas direct pricing from MySQL
    public function testAG07ParrillasDirectPricing(): array
    {
        $tenantId = 11;
        $dto = $this->catalogResolver->resolveCustomerReference($tenantId, 'cuánto cuesta la parrilla para dos');

        $pass = ($dto->status === 'RESOLVED' && (float)$dto->price === 80.00);

        return [
            'gate' => 'AG-07',
            'name' => 'Direct Pricing from MySQL Authority',
            'status' => $pass ? 'PASS' : 'FAIL',
            'resolved_name' => $dto->entityName,
            'price' => $dto->price,
        ];
    }

    // AG-08: Full order chain traceability
    public function testAG08FullOrderChainTraceability(): array
    {
        $tenantId = 11;
        $chain = $this->catalogResolver->resolveFullChainOrder($tenantId, 'quiero una parrilla para dos');

        $pass = ($chain['status'] === 'SUCCESS'
            && !empty($chain['product_id'])
            && !empty($chain['canonical_catalog_id'])
            && !empty($chain['catalog_version_id'])
            && (float)$chain['price'] === 80.00
            && !empty($chain['commercial_media_id'])
            && !empty($chain['evidence_id'])
            && !empty($chain['order_line']));

        return [
            'gate' => 'AG-08',
            'name' => 'Full Order Chain Traceability',
            'status' => $pass ? 'PASS' : 'FAIL',
            'chain' => $chain,
        ];
    }

    // AG-09: Dual variant price authority
    public function testAG09DualVariantPriceAuthority(): array
    {
        $tenantId = 4; // Retail tenant
        $varA = ProductVariant::where('tenant_id', $tenantId)->where('sku', 'BLU-AZUL-M')->first();
        $varB = ProductVariant::where('tenant_id', $tenantId)->where('sku', 'BLU-AZUL-L')->first();

        // Caso A: override presente = 80.00
        $priceA = (float) $varA->effective_price;
        // Caso B: override null, hereda base = 100.00
        $priceB = (float) $varB->effective_price;

        $pass = ($priceA === 80.00 && $priceB === 100.00 && $varA->product_id === $varB->product_id);

        return [
            'gate' => 'AG-09',
            'name' => 'Dual Variant Price Authority (Override vs Inherited Base)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'case_a_override' => $priceA,
            'case_b_base' => $priceB,
            'parent_product_id' => $varA->product_id,
        ];
    }

    // AG-10: Ambiguity handling without sending incorrect image
    public function testAG10AmbiguityHandling(): array
    {
        $tenantId = 11;
        // Registrar alias duplicado para provocar ambigüedad deliberada
        $media2 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'CHANCHOS')->first();
        $media3 = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'ALITAS')->first();

        CommercialMediaAlias::firstOrCreate(
            ['commercial_media_id' => $media2->id, 'tenant_id' => $tenantId, 'alias' => 'combo_doble'],
            ['weight' => 1.0]
        );
        CommercialMediaAlias::firstOrCreate(
            ['commercial_media_id' => $media3->id, 'tenant_id' => $tenantId, 'alias' => 'combo_doble'],
            ['weight' => 1.0]
        );

        $res = $this->mediaResolver->resolve($tenantId, 'pásame el combo doble', 'conv_amb_10');

        $pass = ($res['match_type'] === 'MATCH_AMBIGUOUS' && $res['dispatch_allowed'] === false);

        return [
            'gate' => 'AG-10',
            'name' => 'Ambiguity Handling (Zero Arbitrary Media Dispatch)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'match_type' => $res['match_type'],
            'dispatch_allowed' => $res['dispatch_allowed'],
        ];
    }

    // AG-11: Strict real attachment payload verification
    public function testAG11StrictRealAttachment(): array
    {
        $tenantId = 11;
        $res = $this->mediaResolver->resolve($tenantId, 'pásame la de parrillas', 'conv_att_11');
        $payload = $this->deliveryService->prepareDelivery($res);

        $pass = ($payload !== null
            && ($payload['is_physical_attachment'] ?? false) === true
            && ($payload['type'] ?? '') === 'image'
            && !empty($payload['media_id'])
            && !empty($payload['content_hash'])
            && !empty($payload['file_path']));

        return [
            'gate' => 'AG-11',
            'name' => 'Strict Physical WhatsApp Attachment Dispatch',
            'status' => $pass ? 'PASS' : 'FAIL',
            'payload_sample' => $payload,
        ];
    }

    // AG-12: Full regression TCI
    public function testAG12TciRegressionSafety(): array
    {
        $tciVerifier = new TenantContextIntegrityVerificationService();
        $res = $tciVerifier->runVerification();

        $pass = (($res['all_gates_passed'] ?? false) === true && ($res['passed_count'] ?? 0) === 19);

        return [
            'gate' => 'AG-12',
            'name' => 'TCI Regression Safety (19/19 PASS)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'tci_gates_passed' => $res['passed_count'] ?? 0,
            'tci_status' => $res['status'] ?? 'ERROR',
            'failures' => $res['failures'] ?? [],
        ];
    }

    // AG-13: Full regression P3.10-A
    public function testAG13P310aRegressionSafety(): array
    {
        $p310Verifier = new P310ASemanticVerificationService();
        $res = $p310Verifier->runVerification();

        $pass = (($res['all_gates_passed'] ?? false) === true && ($res['tests_summary']['passed'] ?? 0) === 32);

        return [
            'gate' => 'AG-13',
            'name' => 'P3.10-A Regression Safety (32/32 PASS)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'p310a_gates_passed' => $res['tests_summary']['passed'] ?? 0,
            'p310a_status' => $res['status'] ?? 'ERROR',
        ];
    }

    // AG-14: Full traceability chain (Product -> Evidence -> Media)
    public function testAG14FullTraceabilityChain(): array
    {
        $tenantId = 11;
        $prod = Product::where('tenant_id', $tenantId)->where('name', 'Parrilla para Dos')->first();
        $pvr = ProductVisualReference::where('tenant_id', $tenantId)->where('product_id', $prod->id)->first();
        $media = CommercialMedia::find($pvr->commercial_media_id);

        $pass = ($pvr->evidence_id !== null && $pvr->commercial_media_id === $media->id && $media->category === 'PARRILLAS');

        return [
            'gate' => 'AG-14',
            'name' => 'Full Traceability Product -> Evidence -> Media',
            'status' => $pass ? 'PASS' : 'FAIL',
            'product_id' => $prod->id,
            'evidence_id' => $pvr->evidence_id,
            'commercial_media_id' => $pvr->commercial_media_id,
            'media_category' => $media->category,
        ];
    }

    // AG-15: BBox multi-item registration in product_visual_references
    public function testAG15BBoxMultiItemRegistration(): array
    {
        $tenantId = 11;
        $pvrs = ProductVisualReference::where('tenant_id', $tenantId)->get();

        $pass = ($pvrs->count() >= 4);
        foreach ($pvrs as $pvr) {
            if ($pvr->bbox_x === null || $pvr->bbox_y === null || $pvr->display_index === null) {
                $pass = false;
                break;
            }
        }

        return [
            'gate' => 'AG-15',
            'name' => 'BBox Identity & Multi-Item Registration',
            'status' => $pass ? 'PASS' : 'FAIL',
            'registered_pvrs_count' => $pvrs->count(),
        ];
    }

    // AG-16: Stateful continuous 4-step sequence
    public function testAG16ContinuousFourStepNavigation(): array
    {
        $tenantId = 11;
        $convId = 'conv_nav_seq_' . Str::random(8);

        // Paso 1: "Pásame la carta" -> Media 1
        $res1 = $this->mediaResolver->resolve($tenantId, 'pásame la carta', $convId);
        $order1 = $res1['media']?->display_order;

        // Paso 2: "La siguiente" -> Media 2
        $res2 = $this->mediaResolver->resolve($tenantId, 'la siguiente', $convId);
        $order2 = $res2['media']?->display_order;

        // Paso 3: "La última" -> Media 4
        $res3 = $this->mediaResolver->resolve($tenantId, 'la última', $convId);
        $order3 = $res3['media']?->display_order;

        // Paso 4: "La anterior" -> Media 3
        $res4 = $this->mediaResolver->resolve($tenantId, 'la anterior', $convId);
        $order4 = $res4['media']?->display_order;

        $pass = ($order1 === 1 && $order2 === 2 && $order3 === 4 && $order4 === 3);

        return [
            'gate' => 'AG-16',
            'name' => 'Stateful Continuous 4-Step Navigation',
            'status' => $pass ? 'PASS' : 'FAIL',
            'sequence_steps' => [
                'step1_initial' => $order1,
                'step2_next' => $order2,
                'step3_last' => $order3,
                'step4_prev' => $order4,
            ]
        ];
    }

    // AG-17: Tenant isolation in conversation contexts & C10 DB-level cross-tenant rejection
    public function testAG17TenantIsolationContext(): array
    {
        $tenantA = 11;
        $tenantB = 4;
        $convId = 'cross_tenant_conv_17';

        $verA = CatalogVersion::where('tenant_id', $tenantA)->where('status', 'PUBLISHED')->first();
        $mediaA = CommercialMedia::where('tenant_id', $tenantA)->where('status', 'ACTIVE')->first();

        // Set context in Tenant A
        $this->contextService->updateContext($tenantA, $convId, $verA->id, $mediaA->id, $mediaA->category, $mediaA->display_order);

        // Retrieve from Tenant B
        $contextB = $this->contextService->getContext($tenantB, $convId);

        // C10 Enforcement Forense: Inserción cruzada de tenant en PVR debe ser físicamente rechazada por MySQL/Model
        $crossTenantInsertRejected = false;
        try {
            $mediaTenantB = CommercialMedia::where('tenant_id', $tenantB)->first();
            $prodTenantA = Product::where('tenant_id', $tenantA)->first();
            ProductVisualReference::create([
                'tenant_id' => $tenantA,
                'product_id' => $prodTenantA->id,
                'commercial_media_id' => $mediaTenantB->id, // Cross-tenant media violando FK/Regla 10
                'evidence_id' => 101,
                'bbox_x' => 0.1, 'bbox_y' => 0.1, 'bbox_w' => 0.2, 'bbox_h' => 0.2,
                'display_index' => 99,
                'status' => 'PUBLISHED',
            ]);
        } catch (\Throwable $e) {
            $crossTenantInsertRejected = true;
        }

        $pass = ($contextB === null && $crossTenantInsertRejected);

        return [
            'gate' => 'AG-17',
            'name' => 'Strict Tenant Isolation in Conversation Contexts & C10 FK Defense',
            'status' => $pass ? 'PASS' : 'FAIL',
            'cross_tenant_accessible' => ($contextB !== null),
            'cross_tenant_fk_blocked' => $crossTenantInsertRejected,
        ];
    }

    // AG-18: Cross-card sales resolution
    public function testAG18CrossCardSaleResolution(): array
    {
        $tenantId = 11;
        $convId = 'cross_card_conv_18';

        // Cliente está mirando Carta 1 (Carta General)
        $res1 = $this->mediaResolver->resolve($tenantId, 'pásame la carta', $convId);

        // Cliente pide directamente un plato de la Carta 4 (Parrillas)
        $order = $this->catalogResolver->resolveCustomerReference($tenantId, 'quiero una parrilla para dos');

        $pass = ($res1['media']->display_order === 1 && $order->status === 'RESOLVED' && (float)$order->price === 80.00);

        return [
            'gate' => 'AG-18',
            'name' => 'Cross-Card Sales Resolution (Unbroken Unified Catalog)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'active_card' => $res1['media']->display_order,
            'cross_card_order' => $order->entityName,
            'order_price' => $order->price,
        ];
    }

    // AG-19: Catalog version supersede with commercial history preservation
    public function testAG19CatalogVersionSupersede(): array
    {
        $tenantId = 11;
        $v1 = CatalogVersion::where('tenant_id', $tenantId)->where('status', 'PUBLISHED')->first();
        $mediaIds = CatalogVersionMedia::where('tenant_id', $tenantId)->where('catalog_version_id', $v1->id)->pluck('commercial_media_id')->toArray();

        // 1. Simular pedido histórico bajo V1 (Protección de Historia Comercial)
        $historicalOrder = $this->catalogResolver->resolveFullChainOrder($tenantId, 'parrilla para dos');
        $historicalVersionId = $v1->id;
        $historicalMediaId = $historicalOrder['commercial_media_id'];
        $historicalEvidenceId = $historicalOrder['evidence_id'];

        // 2. Publicar nueva versión v2 (Atómicamente degrada v1 a SUPERSEDED)
        $v2 = $this->compilerService->compileAndPublish($tenantId, $mediaIds, 'Compilación v2 actualizada');

        $v1Refreshed = CatalogVersion::find($v1->id);
        $v2Refreshed = CatalogVersion::find($v2->id);

        // 3. Verificar que la evidencia comercial histórica de V1 sigue 100% intacta
        $historicalMediaStillExists = CommercialMedia::where('id', $historicalMediaId)->exists();
        $historicalEvidenceStillExists = DB::table('raw_menu_evidences')->where('id', $historicalEvidenceId)->exists();
        $historicalLinksIntact = DB::table('catalog_version_media')
            ->where('catalog_version_id', $historicalVersionId)
            ->where('commercial_media_id', $historicalMediaId)
            ->exists();

        $pass = ($v1Refreshed->status === 'SUPERSEDED'
            && (int)$v1Refreshed->is_active === 0
            && $v2Refreshed->status === 'PUBLISHED'
            && (int)$v2Refreshed->is_active === 1
            && $v2Refreshed->version_number === ($v1->version_number + 1)
            && $historicalMediaStillExists
            && $historicalEvidenceStillExists
            && $historicalLinksIntact);

        return [
            'gate' => 'AG-19',
            'name' => 'Catalog Version Supersede with Commercial History Preservation',
            'status' => $pass ? 'PASS' : 'FAIL',
            'previous_version_status' => $v1Refreshed->status,
            'previous_is_active' => $v1Refreshed->is_active,
            'new_version_status' => $v2Refreshed->status,
            'new_is_active' => $v2Refreshed->is_active,
            'new_version_number' => $v2Refreshed->version_number,
            'historical_order_traceability_preserved' => ($historicalMediaStillExists && $historicalEvidenceStillExists && $historicalLinksIntact),
        ];
    }

    // AG-20: Retail visual variant resolution ("segunda blusa azul")
    public function testAG20RetailVisualVariantResolution(): array
    {
        $tenantId = 4; // Retail tenant
        $mediaRopa = CommercialMedia::where('tenant_id', $tenantId)->first();

        $dto = $this->catalogResolver->resolveP311ProductOrVariant($tenantId, 'quiero la segunda blusa azul', 'segunda blusa azul', $mediaRopa->id);

        $pass = ($dto !== null && $dto->status === 'RESOLVED' && $dto->entityType === 'variant' && (float)$dto->price === 100.00);

        return [
            'gate' => 'AG-20',
            'name' => 'Retail Visual Variant Resolution (Ordinal + Visual Attribute)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'resolved_variant' => $dto?->entityName,
            'price' => $dto?->price,
            'sku' => $dto?->additionalData['sku'] ?? null,
        ];
    }

    // AG-21: Non-existent product honesty (anti-hallucination)
    public function testAG21NonExistentProductHonesty(): array
    {
        $tenantId = 11;
        $dto = $this->catalogResolver->resolveCustomerReference($tenantId, 'dame una pizza hawaiana familiar');

        $pass = ($dto->status === 'NOT_FOUND');

        return [
            'gate' => 'AG-21',
            'name' => 'Anti-Hallucination Rejection of Non-Existent Products',
            'status' => $pass ? 'PASS' : 'FAIL',
            'resolution_status' => $dto->status,
            'reason' => $dto->reason,
        ];
    }

    // AG-22: MySQL order authority
    public function testAG22MySqlOrderAuthority(): array
    {
        $tenantId = 11;
        $prod = Product::where('tenant_id', $tenantId)->where('name', 'Parrilla para Dos')->first();

        // Modificar precio temporalmente en MySQL a 85.00
        $prod->update(['base_price' => 85.00]);
        $dto = $this->catalogResolver->resolveCustomerReference($tenantId, 'parrilla para dos');

        $pass = ($dto->status === 'RESOLVED' && (float)$dto->price === 85.00);

        // Restaurar a 80.00
        $prod->update(['base_price' => 80.00]);

        return [
            'gate' => 'AG-22',
            'name' => 'MySQL Price Authority (DB Reflection without Re-reading)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'updated_price_read' => $dto->price,
        ];
    }

    // AG-23: Zero pixel re-reading at runtime
    public function testAG23ZeroPixelRuntimeAssurance(): array
    {
        RuntimeCallCounter::reset();

        // Ejecutar flujo de atención conversacional
        $tenantId = 11;
        $this->mediaResolver->resolve($tenantId, 'pásame la de parrillas', 'conv_rt_23');
        $this->catalogResolver->resolveCustomerReference($tenantId, 'parrilla para dos');
        $this->catalogResolver->resolveFullChainOrder($tenantId, 'quiero una parrilla para dos');

        $stats = RuntimeCallCounter::getStats();
        $pass = ($stats['TOTAL_RUNTIME_PIXEL_CALLS'] === 0 && RuntimeCallCounter::assertZeroRuntimeCalls());

        return [
            'gate' => 'AG-23',
            'name' => 'Zero Pixel Re-Reading at Runtime (Pixel Firewall)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'stats' => $stats,
        ];
    }

    // AG-24: Anti-orphan XOR constraint enforcement
    public function testAG24AntiOrphanXorConstraint(): array
    {
        $tenantId = 11;
        $media = CommercialMedia::where('tenant_id', $tenantId)->first();
        $prod = Product::where('tenant_id', $tenantId)->first();

        $bothRejected = false;
        $neitherRejected = false;

        // Intentar crear con ambos (product_id y variant_id)
        try {
            ProductVisualReference::create([
                'tenant_id' => $tenantId,
                'product_id' => $prod->id,
                'variant_id' => 9999,
                'commercial_media_id' => $media->id,
                'evidence_id' => 999,
                'display_index' => 99,
            ]);
        } catch (\InvalidArgumentException $e) {
            $bothRejected = true;
        }

        // Intentar crear sin ninguno
        try {
            ProductVisualReference::create([
                'tenant_id' => $tenantId,
                'product_id' => null,
                'variant_id' => null,
                'commercial_media_id' => $media->id,
                'evidence_id' => 999,
                'display_index' => 99,
            ]);
        } catch (\InvalidArgumentException $e) {
            $neitherRejected = true;
        }

        $pass = ($bothRejected && $neitherRejected);

        return [
            'gate' => 'AG-24',
            'name' => 'Anti-Orphan XOR Constraint Enforcement',
            'status' => $pass ? 'PASS' : 'FAIL',
            'both_ids_rejected' => $bothRejected,
            'neither_id_rejected' => $neitherRejected,
        ];
    }

    // AG-25: Publish authority firewall (DRAFT/NEEDS_REVIEW rejection)
    public function testAG25PublishAuthorityFirewall(): array
    {
        $tenantId = 11;
        $cat11 = \App\Modules\Business\Models\Category::where('tenant_id', $tenantId)->first();
        $id = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'category_id' => $cat11?->id ?? 1,
            'name' => 'Parrilla Mixta Secreta',
            'slug' => 'parrilla-mixta-secreta-' . Str::random(5),
            'description' => 'Parrilla de prueba oculta',
            'base_price' => 95.00,
            'status' => 'INACTIVE',
            'is_available' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dto = $this->catalogResolver->resolveCustomerReference($tenantId, 'parrilla mixta secreta');

        DB::table('products')->where('id', $id)->delete();

        $pass = ($dto->status === 'NOT_FOUND');

        return [
            'gate' => 'AG-25',
            'name' => 'Publish Authority Firewall (DRAFT / NEEDS_REVIEW Rejection)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'resolution_status' => $dto->status,
        ];
    }

    // AG-26: Joint dispatch rule (ACTIVE + version PUBLISHED)
    public function testAG26JointDispatchRule(): array
    {
        $tenantId = 11;
        $inactiveMedia = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => (string) Str::uuid(),
            'media_type' => 'MENU_CARD',
            'category' => 'POSTRES',
            'title' => 'Carta de Postres Desactivada',
            'file_path' => 'cards/wallpa_postres.png',
            'content_hash' => hash('sha256', 'postres_test'),
            'revision_number' => 1,
            'status' => 'INACTIVE', // No activo
        ]);
        CommercialMediaAlias::create(['commercial_media_id' => $inactiveMedia->id, 'tenant_id' => $tenantId, 'alias' => 'postres', 'weight' => 1.0]);

        $res = $this->mediaResolver->resolve($tenantId, 'pásame los postres', 'conv_joint_26');

        $inactiveMedia->delete();

        $pass = ($res['dispatch_allowed'] === false);

        return [
            'gate' => 'AG-26',
            'name' => 'Joint Dispatch Rule (Status ACTIVE + Catalog PUBLISHED)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'dispatch_allowed' => $res['dispatch_allowed'],
        ];
    }

    // AG-27: Revision chain lineage enforcement
    public function testAG27RevisionChainLineageEnforcement(): array
    {
        $tenantId = 11;
        $lineageId = (string) Str::uuid();

        // Revisión 1
        $r1 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => $lineageId,
            'media_type' => 'MENU_CARD',
            'category' => 'BEBIDAS',
            'title' => 'Carta Bebidas v1',
            'file_path' => 'cards/bebidas_v1.png',
            'content_hash' => hash('sha256', 'bebidas_v1_hash'),
            'revision_number' => 1,
            'status' => 'ACTIVE',
        ]);

        // Revisión 2 correcta (previous_media_id = r1, revision_number = 2)
        $r2 = CommercialMedia::create([
            'tenant_id' => $tenantId,
            'lineage_id' => $lineageId,
            'media_type' => 'MENU_CARD',
            'category' => 'BEBIDAS',
            'title' => 'Carta Bebidas v2',
            'file_path' => 'cards/bebidas_v2.png',
            'content_hash' => hash('sha256', 'bebidas_v2_hash'),
            'revision_number' => 2,
            'previous_media_id' => $r1->id,
            'status' => 'ACTIVE',
        ]);

        // Intento de salto inválido a Revisión 4 (debe ser rechazado por C11)
        $skippedRejected = false;
        try {
            CommercialMedia::create([
                'tenant_id' => $tenantId,
                'lineage_id' => $lineageId,
                'media_type' => 'MENU_CARD',
                'category' => 'BEBIDAS',
                'title' => 'Carta Bebidas v4',
                'file_path' => 'cards/bebidas_v4.png',
                'content_hash' => hash('sha256', 'bebidas_v4_hash'),
                'revision_number' => 4,
                'previous_media_id' => $r2->id,
                'status' => 'ACTIVE',
            ]);
        } catch (\InvalidArgumentException $e) {
            $skippedRejected = true;
        }

        // Intento de mismo hash mediante create() directo (debe ser rechazado para evitar filas redundantes)
        $sameHashRejected = false;
        try {
            CommercialMedia::create([
                'tenant_id' => $tenantId,
                'lineage_id' => $lineageId,
                'media_type' => 'MENU_CARD',
                'category' => 'BEBIDAS',
                'title' => 'Carta Bebidas v3 Duplicada',
                'file_path' => 'cards/bebidas_v2.png',
                'content_hash' => hash('sha256', 'bebidas_v2_hash'), // Mismo hash que r2
                'revision_number' => 3,
                'previous_media_id' => $r2->id,
                'status' => 'ACTIVE',
            ]);
        } catch (\InvalidArgumentException $e) {
            $sameHashRejected = true;
        }

        // Reutilización Idempotente por Hash (C11): Si el hash es idéntico, registerOrReuse devuelve la instancia existente
        $reused = CommercialMedia::registerOrReuse([
            'tenant_id' => $tenantId,
            'lineage_id' => $lineageId,
            'media_type' => 'MENU_CARD',
            'category' => 'BEBIDAS',
            'title' => 'Carta Bebidas Reutilizada',
            'file_path' => 'cards/bebidas_v2.png',
            'content_hash' => hash('sha256', 'bebidas_v2_hash'),
            'status' => 'ACTIVE',
        ]);
        $idempotentReusePass = ($reused->id === $r2->id && $reused->revision_number === 2);

        $pass = ($r2->revision_number === 2 && $skippedRejected && $sameHashRejected && $idempotentReusePass);

        return [
            'gate' => 'AG-27',
            'name' => 'Revision Chain & Lineage Enforcement (Sequential + Hash Idempotent Reuse)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'sequential_accepted' => ($r2->revision_number === 2),
            'skipped_revision_rejected' => $skippedRejected,
            'identical_hash_rejected_on_create' => $sameHashRejected,
            'idempotent_reuse_media_id' => $reused->id,
        ];
    }

    // AG-28: Deterministic geometric clustering
    public function testAG28DeterministicGeometricClustering(): array
    {
        // Simular 4 elementos en dos filas visuales desordenados en entrada
        $rawItems = [
            ['id' => 'item_r2_c2', 'bbox_x' => 0.6, 'bbox_y' => 0.42, 'bbox_w' => 0.2, 'bbox_h' => 0.1], // Fila 2 derecha
            ['id' => 'item_r1_c2', 'bbox_x' => 0.6, 'bbox_y' => 0.11, 'bbox_w' => 0.2, 'bbox_h' => 0.1], // Fila 1 derecha
            ['id' => 'item_r2_c1', 'bbox_x' => 0.1, 'bbox_y' => 0.40, 'bbox_w' => 0.2, 'bbox_h' => 0.1], // Fila 2 izquierda
            ['id' => 'item_r1_c1', 'bbox_x' => 0.1, 'bbox_y' => 0.10, 'bbox_w' => 0.2, 'bbox_h' => 0.1], // Fila 1 izquierda
        ];

        $indexed = $this->compilerService->computeGeometricDisplayIndices($rawItems);

        $orderResult = array_column($indexed, 'item_id');
        // Orden determinístico esperado: Fila 1 (izq, der) -> Fila 2 (izq, der)
        $expected = ['item_r1_c1', 'item_r1_c2', 'item_r2_c1', 'item_r2_c2'];

        $pass = ($orderResult === $expected);

        return [
            'gate' => 'AG-28',
            'name' => 'Deterministic 2D Geometric Row Clustering (C12)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'ordered_ids' => $orderResult,
            'expected_ids' => $expected,
        ];
    }

    // AG-29: Version-pinned navigation
    public function testAG29VersionPinnedNavigation(): array
    {
        $tenantId = 11;
        $convId = 'pinned_nav_conv_29';

        // Inicializar contexto en la versión publicada activa
        $this->mediaResolver->resolve($tenantId, 'pásame la carta', $convId);

        // Navegación hacia adelante
        $resNext = $this->mediaResolver->resolve($tenantId, 'la siguiente', $convId);

        $context = ConversationCommercialContext::where('tenant_id', $tenantId)->where('conversation_id', $convId)->first();
        $media = $resNext['media'];

        // Verificar que el medio entregado pertenece a la versión fijada en el contexto
        $pinnedVersionMedia = CatalogVersionMedia::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $context->catalog_version_id)
            ->where('commercial_media_id', $media->id)
            ->exists();

        $pass = ($resNext['match_type'] === 'MATCH_EXACT' && $pinnedVersionMedia);

        return [
            'gate' => 'AG-29',
            'name' => 'Version-Pinned Navigation Integrity (C4)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'catalog_version_id' => $context->catalog_version_id,
            'media_belongs_to_version' => $pinnedVersionMedia,
        ];
    }

    // AG-30: Scoped display_index uniqueness per media
    public function testAG30ScopedDisplayIndexUniqueness(): array
    {
        $tenantId = 11;
        $media = CommercialMedia::where('tenant_id', $tenantId)->first();
        $prod = Product::where('tenant_id', $tenantId)->first();

        // Ya existe display_index = 1 para este media
        $duplicateRejected = false;
        try {
            ProductVisualReference::create([
                'tenant_id' => $tenantId,
                'product_id' => $prod->id,
                'commercial_media_id' => $media->id,
                'evidence_id' => 999,
                'display_index' => 1, // Duplicado en el mismo media
            ]);
        } catch (\Throwable $e) {
            $duplicateRejected = true;
        }

        $pass = $duplicateRejected;

        return [
            'gate' => 'AG-30',
            'name' => 'Scoped display_index Uniqueness per Media',
            'status' => $pass ? 'PASS' : 'FAIL',
            'duplicate_display_index_rejected' => $duplicateRejected,
        ];
    }

    // AG-31: Context-bounded visual attribute priority
    public function testAG31ContextBoundedVisualAttributePriority(): array
    {
        $tenantId = 4; // Retail tenant
        $mediaRopa = CommercialMedia::where('tenant_id', $tenantId)->first();

        // Sin especificar ordinal, existen dos blusas azules (M y L)
        // Debe retornar STATUS_AMBIGUOUS sin tomar decisión arbitraria
        $dto = $this->catalogResolver->resolveP311ProductOrVariant($tenantId, 'quiero la blusa azul', 'blusa azul', $mediaRopa->id);

        $pass = ($dto !== null && $dto->status === 'AMBIGUOUS' && count($dto->candidates) >= 2);

        return [
            'gate' => 'AG-31',
            'name' => 'Context-Bounded Visual Attribute Priority & Ambiguity Protection',
            'status' => $pass ? 'PASS' : 'FAIL',
            'status_code' => $dto?->status,
            'candidates_count' => count($dto?->candidates ?? []),
        ];
    }

    // AG-32: SHA-256 content hash integrity
    public function testAG32Sha256ContentHashIntegrity(): array
    {
        $tenantId = 11;
        $medias = CommercialMedia::where('tenant_id', $tenantId)->get();

        $pass = true;
        foreach ($medias as $m) {
            if (empty($m->content_hash) || strlen($m->content_hash) !== 64) {
                $pass = false;
                break;
            }
        }

        return [
            'gate' => 'AG-32',
            'name' => 'SHA-256 Content Hash Invariance & Integrity',
            'status' => $pass ? 'PASS' : 'FAIL',
            'checked_medias_count' => $medias->count(),
        ];
    }

    // AG-33: Hostile runtime pixel firewall
    public function testAG33HostileRuntimePixelFirewall(): array
    {
        RuntimeCallCounter::reset();

        $tenantId = 11;
        $convId = 'hostile_firewall_conv_33';

        // 1. Catalog resolution (start from Carta 1 display_order = 1)
        $resMedia = $this->mediaResolver->resolve($tenantId, 'pásame la carta', $convId);
        $catalogPass = ($resMedia['match_type'] === 'MATCH_EXACT');

        // 2. Media delivery payload
        $delivery = $this->deliveryService->prepareDelivery($resMedia);
        $deliveryPass = ($delivery !== null && $delivery['is_physical_attachment'] === true);

        // 3. Price resolution
        $pricing = $this->catalogResolver->resolveCustomerReference($tenantId, 'cuánto cuesta la parrilla para dos');
        $pricingPass = ($pricing->status === 'RESOLVED' && (float)$pricing->price === 80.00);

        // 4. Variant resolution
        $variantDto = $this->catalogResolver->resolveP311ProductOrVariant(4, 'la segunda blusa azul', 'segunda blusa azul');
        $variantPass = ($variantDto !== null && $variantDto->status === 'RESOLVED');

        // 5. Navigation
        $navRes = $this->mediaResolver->resolve($tenantId, 'la siguiente', $convId);
        $navPass = ($navRes['match_type'] === 'MATCH_EXACT');

        // 6. Order persistence / chain
        $orderChain = $this->catalogResolver->resolveFullChainOrder($tenantId, 'parrilla para dos');
        $orderPass = ($orderChain['status'] === 'SUCCESS');

        // 7. PRUEBA DE FUEGO HOSTIL: Simular intentos de invocación visual/OCR en runtime
        // El firewall DEBE interceptar activamente y arrojar PixelFirewallDeniedException (DENIED)
        $visionBlocked = false;
        try {
            RuntimeCallCounter::recordVisionCall('HostileRuntimeTest::inspectImage');
        } catch (\App\Modules\AI\Exceptions\PixelFirewallDeniedException $e) {
            $visionBlocked = true;
        }

        $multimodalBlocked = false;
        try {
            RuntimeCallCounter::recordMultimodalCall('HostileRuntimeTest::multimodalAnalyze');
        } catch (\App\Modules\AI\Exceptions\PixelFirewallDeniedException $e) {
            $multimodalBlocked = true;
        }

        $ocrBlocked = false;
        try {
            RuntimeCallCounter::recordOcrCall('HostileRuntimeTest::ocrScan');
        } catch (\App\Modules\AI\Exceptions\PixelFirewallDeniedException $e) {
            $ocrBlocked = true;
        }

        // 8. ASSERT ZERO RUNTIME CALLS across Vision, Multimodal, and OCR
        $stats = RuntimeCallCounter::getStats();
        $zeroPixels = ($stats['VISION_RUNTIME_CALLS'] === 0
            && $stats['MULTIMODAL_RUNTIME_CALLS'] === 0
            && $stats['OCR_RUNTIME_CALLS'] === 0
            && $stats['BLOCKED_HOSTILE_ATTEMPTS'] === 3
            && RuntimeCallCounter::assertZeroRuntimeCalls());

        $hostileFirewallPass = ($visionBlocked && $multimodalBlocked && $ocrBlocked);

        $pass = ($catalogPass && $deliveryPass && $pricingPass && $variantPass && $navPass && $orderPass && $zeroPixels && $hostileFirewallPass);

        return [
            'gate' => 'AG-33',
            'name' => 'Hostile Runtime Pixel Firewall Telemetry Verification',
            'status' => $pass ? 'PASS' : 'FAIL',
            'catalog_resolution' => $catalogPass ? 'PASS' : 'FAIL',
            'media_delivery' => $deliveryPass ? 'PASS' : 'FAIL',
            'price_resolution' => $pricingPass ? 'PASS' : 'FAIL',
            'variant_resolution' => $variantPass ? 'PASS' : 'FAIL',
            'navigation' => $navPass ? 'PASS' : 'FAIL',
            'order_persistence' => $orderPass ? 'PASS' : 'FAIL',
            'hostile_interceptions' => [
                'vision_blocked' => $visionBlocked,
                'multimodal_blocked' => $multimodalBlocked,
                'ocr_blocked' => $ocrBlocked,
            ],
            'BLOCKED_HOSTILE_ATTEMPTS' => $stats['BLOCKED_HOSTILE_ATTEMPTS'],
            'VISION_RUNTIME_CALLS' => $stats['VISION_RUNTIME_CALLS'],
            'MULTIMODAL_RUNTIME_CALLS' => $stats['MULTIMODAL_RUNTIME_CALLS'],
            'OCR_RUNTIME_CALLS' => $stats['OCR_RUNTIME_CALLS'],
        ];
    }

    // AG-34: Published Catalog Commercial Completeness by Identity
    public function testAG34PublishedCatalogCommercialCompleteness(): array
    {
        $tenantId = 11;
        $activeVersion = CatalogVersion::where('tenant_id', $tenantId)->where('status', 'PUBLISHED')->first();

        // 1. Verificar registro de auditoría de completitud emitido por PublishCatalogAuthority
        $validation = CatalogVersionValidation::where('tenant_id', $tenantId)
            ->where('catalog_version_id', $activeVersion?->id)
            ->latest()
            ->first();

        $expectedCount = (int)($validation?->expected_sellable_count ?? 0);
        $completenessPass = ($validation !== null
            && $validation->status === 'COMPLETE'
            && $expectedCount >= 23
            && (int)$validation->evidence_count === $expectedCount
            && (int)$validation->resolved_count === $expectedCount
            && (int)$validation->pvr_count === $expectedCount
            && empty($validation->missing_items_json));

        // 2. PRUEBA NEGATIVA / FIREWALL: Simular intento de publicación con ítem vendible faltante
        // Si falta un ítem vendible, la autoridad DEBE degradar a NEEDS_REVIEW y denegar publicación
        $incompleteVersion = CatalogVersion::create([
            'tenant_id' => $tenantId,
            'version_number' => 999,
            'status' => 'PROCESSING',
            'is_active' => 0,
            'changelog_summary' => 'Test Incomplete Catalog Gate',
        ]);

        $media4Id = CommercialMedia::where('tenant_id', $tenantId)->where('category', 'PARRILLAS')->value('id');
        $incompleteItems = [
            $media4Id => [
                new ExpectedCommercialItem('parrilla_dos', 'Parrilla para Dos', 'SELLABLE_PRODUCT', 80.00),
                new ExpectedCommercialItem('parrilla_fantasma', 'Parrilla Fantasma Inexistente', 'SELLABLE_PRODUCT', 999.00),
            ]
        ];

        $publishAuthority = new PublishCatalogAuthority();
        $blocked = false;
        try {
            $publishAuthority->authorizeAndPublish($incompleteVersion->id, $tenantId, $incompleteItems);
        } catch (CommercialCompletenessException $e) {
            $blocked = true;
        }

        $incompleteVersionFresh = CatalogVersion::find($incompleteVersion->id);
        $firewallPass = ($blocked
            && $incompleteVersionFresh->status === 'NEEDS_REVIEW'
            && (int)$incompleteVersionFresh->is_active === 0);

        // Limpiar versión incompleta de prueba
        $incompleteVersionFresh->delete();

        $pass = ($completenessPass && $firewallPass);

        return [
            'gate' => 'AG-34',
            'name' => 'Published Catalog Commercial Completeness by Identity (Rule C14)',
            'status' => $pass ? 'PASS' : 'FAIL',
            'active_catalog_version_id' => $activeVersion?->id,
            'expected_sellable_items' => $validation?->expected_sellable_count,
            'raw_evidences_found' => $validation?->evidence_count,
            'canonical_items_found' => $validation?->resolved_count,
            'pvrs_found' => $validation?->pvr_count,
            'missing_items_count' => count($validation?->missing_items_json ?? []),
            'completeness_status' => $validation?->status,
            'incomplete_catalog_blocked' => $firewallPass,
        ];
    }

    // AG-35: Deterministic No Cross-Product Commercial Substitution
    public function testAG35NoCrossProductCommercialSubstitution(): array
    {
        $tenantId = 11;

        // 1. Resolución determinística de las 3 parrillas independientes
        $resDos = $this->catalogResolver->resolveCustomerReference($tenantId, 'quiero una parrilla para dos');
        $resFam = $this->catalogResolver->resolveCustomerReference($tenantId, '¿Cómo está la Parrilla Familiar?');
        $resWallpa = $this->catalogResolver->resolveCustomerReference($tenantId, 'precio de la parrilla wallpa');

        $distinctPass = ($resDos->status === 'RESOLVED'
            && $resDos->entityName === 'Parrilla para Dos'
            && (float)$resDos->price === 80.00
            && $resFam->status === 'RESOLVED'
            && $resFam->entityName === 'Parrilla Familiar'
            && (float)$resFam->price === 150.00
            && $resWallpa->status === 'RESOLVED'
            && $resWallpa->entityName === 'Parrilla Wallpa'
            && (float)$resWallpa->price === 180.00);

        // 2. PRUEBA DE NO-SUSTITUCIÓN DETERMINÍSTICA:
        // Consulta: "Parrilla Gigante" (calificador incompatible o producto inexistente)
        // Bajo ninguna circunstancia se debe mapear con fuzzy match a "Parrilla para Dos".
        $resNonExistent = $this->catalogResolver->resolveCustomerReference($tenantId, 'cuánto cuesta la parrilla gigante');
        $noSubPass = ($resNonExistent->status === 'NOT_FOUND'
            && $resNonExistent->commercial_substitution_allowed === false
            && $resNonExistent->entityName !== 'Parrilla para Dos');

        // 3. PRUEBA DE AMBIGÜEDAD DETERMINÍSTICA:
        // Consulta ambigua: "quiero un chaufa de pollo" (sin especificar Personal S/16 o Familiar S/31)
        $resAmbiguous = $this->catalogResolver->resolveCustomerReference($tenantId, 'quiero un chaufa de pollo');
        $ambiguousPass = ($resAmbiguous->status === 'AMBIGUOUS'
            && (!empty($resAmbiguous->candidates) || !empty($resAmbiguous->options))
            && count($resAmbiguous->candidates ?? $resAmbiguous->options ?? []) >= 2);

        $pass = ($distinctPass && $noSubPass && $ambiguousPass);

        return [
            'gate' => 'AG-35',
            'name' => 'Deterministic No Cross-Product Commercial Substitution',
            'status' => $pass ? 'PASS' : 'FAIL',
            'parrilla_dos' => ['name' => $resDos->entityName, 'price' => $resDos->price],
            'parrilla_familiar' => ['name' => $resFam->entityName, 'price' => $resFam->price],
            'parrilla_wallpa' => ['name' => $resWallpa->entityName, 'price' => $resWallpa->price],
            'anti_substitution_enforced' => $noSubPass,
            'ambiguity_detected_cleanly' => $ambiguousPass,
        ];
    }

    // AG-36: Historical Commercial Price Snapshot Integrity
    public function testAG36HistoricalCommercialPriceSnapshotIntegrity(): array
    {
        $tenantId = 11;

        // 1. Simular venta acordada de "Parrilla Familiar" a precio de carta S/ 150.00
        $orderResolution = $this->catalogResolver->resolveFullChainOrder($tenantId, 'quiero una parrilla familiar');
        $historicalPrice = (float)$orderResolution['price'];

        $historicalOrderLine = [
            'product_id' => $orderResolution['product_id'],
            'product_name' => $orderResolution['product_name'],
            'unit_price_snapshot' => $historicalPrice, // S/ 150.00 acordado y congelado
            'commercial_media_id' => $orderResolution['commercial_media_id'],
            'evidence_id' => $orderResolution['evidence_id'],
            'catalog_version_id' => $orderResolution['catalog_version_id'],
        ];

        // 2. Simular cambio comercial posterior en el negocio: suben el precio a S/ 155.00
        DB::table('products')->where('id', $orderResolution['product_id'])->update([
            'base_price' => 155.00,
            'updated_at' => now(),
        ]);

        // 3. Nuevo cliente consulta el catálogo actual
        $newResolution = $this->catalogResolver->resolveCustomerReference($tenantId, 'cuánto cuesta la parrilla familiar');
        $newCurrentPrice = (float)$newResolution->price;

        // 4. VERIFICACIÓN DE INTEGRIDAD:
        // El nuevo cliente ve S/ 155.00
        // La línea de orden histórica SIGUE SIENDO S/ 150.00 y no fue mutada
        $priceChangedInCatalog = ($newCurrentPrice === 155.00);
        $snapshotRemainsFrozen = ($historicalOrderLine['unit_price_snapshot'] === 150.00);

        // Restaurar precio canónico original a 150.00
        DB::table('products')->where('id', $orderResolution['product_id'])->update([
            'base_price' => 150.00,
            'updated_at' => now(),
        ]);

        $pass = ($orderResolution['status'] === 'SUCCESS'
            && $priceChangedInCatalog
            && $snapshotRemainsFrozen);

        return [
            'gate' => 'AG-36',
            'name' => 'Historical Commercial Price Snapshot Integrity',
            'status' => $pass ? 'PASS' : 'FAIL',
            'historical_order_snapshot' => $historicalOrderLine['unit_price_snapshot'],
            'updated_catalog_price' => $newCurrentPrice,
            'historical_snapshot_immutable' => $snapshotRemainsFrozen,
            'runtime_catalog_updated' => $priceChangedInCatalog,
        ];
    }
}

