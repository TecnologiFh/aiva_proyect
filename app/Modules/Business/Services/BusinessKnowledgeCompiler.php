<?php

namespace App\Modules\Business\Services;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Events\BusinessKnowledgeCompiled;
use App\Modules\Business\DTOs\CompiledBusinessKnowledge;
use App\Modules\Business\Repositories\BusinessHourRepository;
use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Business\Repositories\CategoryRepository;
use App\Modules\Business\Repositories\ProductRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BusinessKnowledgeCompiler
{
    public function __construct(
        private readonly BusinessSettingRepository $settingRepository,
        private readonly BusinessHourRepository $hourRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly EventBusInterface $eventBus
    ) {}

    /**
     * Compila y guarda en Redis la base de conocimiento del tenant optimizada para la IA.
     */
    public function compile(int $tenantId): CompiledBusinessKnowledge
    {
        $knowledgeVersion = $this->incrementKnowledgeVersion($tenantId);

        $payload = $this->buildSnapshotPayload($tenantId);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $jsonPayload);

        $compiled = new CompiledBusinessKnowledge(
            tenantId: $tenantId,
            schemaVersion: 1,
            knowledgeVersion: $knowledgeVersion,
            hash: $hash,
            payload: $payload,
            generatedAt: now()->toIso8601String()
        );

        $versionedKey = "aiva:tenant:{$tenantId}:brain:knowledge:v{$knowledgeVersion}";
        $currentPointerKey = "aiva:tenant:{$tenantId}:brain:knowledge:current";

        // Guardar snapshot versionado en Redis por 7 días
        try {
            Redis::connection('cache')->setex($versionedKey, 86400 * 7, $compiled->toJson());
            Redis::connection('cache')->set($currentPointerKey, $versionedKey);
        } catch (\Throwable $e) {
            Cache::put($versionedKey, $compiled->toArray(), now()->addDays(7));
            Cache::put($currentPointerKey, $versionedKey, now()->addDays(7));
        }

        // Disparar evento de compilación completada
        $this->eventBus->dispatch(new BusinessKnowledgeCompiled($compiled));

        return $compiled;
    }

    /**
     * Invalida y fuerza la recompilación atómica del snapshot de la IA.
     */
    public function invalidate(int $tenantId): CompiledBusinessKnowledge
    {
        return $this->compile($tenantId);
    }

    /**
     * Obtiene el snapshot activo actual consumible por el Motor de IA (Sprint 4) con latencia 0ms.
     * Valida internamente que el payload coincida con el tenantId solicitado (TCI-04).
     */
    public function getSnapshot(int $tenantId): ?array
    {
        $currentPointerKey = "aiva:tenant:{$tenantId}:brain:knowledge:current";
        
        try {
            $versionedKey = Redis::connection('cache')->get($currentPointerKey);
            if ($versionedKey) {
                $raw = Redis::connection('cache')->get($versionedKey);
                $data = $raw ? json_decode($raw, true) : null;
                if ($data) {
                    $payloadTenant = (int)($data['metadata']['tenant_id'] ?? $data['tenant_id'] ?? $data['payload']['business']['tenant_id'] ?? 0);
                    if ($payloadTenant > 0 && $payloadTenant !== $tenantId) {
                        throw new \App\Core\Exceptions\TenantContextMismatchException(
                            "Caché contaminada en Redis (TCI-04): La clave [{$versionedKey}] contiene tenant [{$payloadTenant}] pero se solicitó [{$tenantId}]."
                        );
                    }
                    return $data;
                }
            }
        } catch (\App\Core\Exceptions\TenantContextMismatchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $versionedKey = Cache::get($currentPointerKey);
            if ($versionedKey) {
                $data = Cache::get($versionedKey);
                if ($data) {
                    $payloadTenant = (int)($data['metadata']['tenant_id'] ?? $data['tenant_id'] ?? $data['payload']['business']['tenant_id'] ?? 0);
                    if ($payloadTenant > 0 && $payloadTenant !== $tenantId) {
                        throw new \App\Core\Exceptions\TenantContextMismatchException(
                            "Caché contaminada (TCI-04): Se detectó tenant [{$payloadTenant}] en lugar de [{$tenantId}]."
                        );
                    }
                    return $data;
                }
            }
        }

        // Si no existe snapshot en caché, compilarlo en caliente
        return $this->compile($tenantId)->toArray();
    }

    private function buildSnapshotPayload(int $tenantId): array
    {
        $settings = $this->settingRepository->getByTenantId($tenantId);
        $hours = $this->hourRepository->getByTenantId($tenantId);
        $categories = $this->categoryRepository->allByTenant($tenantId);

        $productsPaginator = $this->productRepository->paginate($tenantId, limit: 500);

        return [
            'business' => $settings ? [
                'name' => $settings->business_name,
                'business_type' => $settings->business_type?->value ?? 'RESTAURANT',
                'phone' => $settings->phone,
                'whatsapp_phone' => $settings->whatsapp_phone,
                'address' => $settings->address,
                'timezone' => $settings->timezone,
                'currency' => $settings->currency?->value ?? 'PEN',
                'preferences' => $settings->preferences,
            ] : null,
            'delivery' => $settings ? [
                'enabled' => $settings->delivery_enabled,
                'fee' => (float) $settings->delivery_fee,
                'min_order_amount' => (float) $settings->min_order_amount,
                'terms' => $settings->terms_and_conditions,
            ] : null,
            'hours' => $hours->map(fn($h) => [
                'day' => $h->day_of_week,
                'shift' => $h->shift_number,
                'open' => $h->open_time,
                'close' => $h->close_time,
                'is_closed' => (bool) $h->is_closed,
            ])->toArray(),
            'categories' => $categories->map(fn($c) => [
                'uuid' => $c->uuid,
                'name' => $c->name,
                'description' => $c->description,
                'search_keywords' => $c->search_keywords,
            ])->toArray(),
            'products' => collect($productsPaginator->items())->map(fn($p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'category' => $p->category?->name,
                'type' => $p->product_type?->value ?? 'PRODUCT',
                'base_price' => (float) $p->base_price,
                'status' => $p->status?->value ?? 'ACTIVE',
                'is_available' => (bool) $p->is_available,
                'search_keywords' => $p->search_keywords,
                'options' => $p->options->map(fn($opt) => [
                    'name' => $opt->name,
                    'is_required' => (bool) $opt->is_required,
                    'min_selectable' => $opt->min_selectable,
                    'max_selectable' => $opt->max_selectable,
                    'values' => $opt->values->map(fn($val) => [
                        'name' => $val->name,
                        'price_modifier' => (float) $val->price_modifier,
                        'is_default' => (bool) $val->is_default,
                    ])->toArray(),
                ])->toArray(),
            ])->toArray(),
        ];
    }

    private function incrementKnowledgeVersion(int $tenantId): int
    {
        $versionKey = "tenant:{$tenantId}:brain:knowledge:version";
        try {
            return (int) Redis::connection('cache')->incr($versionKey);
        } catch (\Throwable $e) {
            $current = (int) Cache::get($versionKey, 0) + 1;
            Cache::put($versionKey, $current, now()->addDays(30));
            return $current;
        }
    }
}
