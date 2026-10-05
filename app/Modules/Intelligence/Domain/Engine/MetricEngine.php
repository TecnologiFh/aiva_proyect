<?php

namespace App\Modules\Intelligence\Domain\Engine;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\Domain\Engine\Cache\IntelligenceCacheService;
use App\Modules\Intelligence\Domain\Registry\MetricRegistry;
use App\Modules\Intelligence\Domain\Values\IntelligenceResult;
use App\Modules\Intelligence\Domain\Values\PeriodComparison;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MetricEngine
{
    public function __construct(
        private readonly MetricRegistry $registry,
        private readonly IntelligenceCacheService $cacheService,
        private readonly Container $container
    ) {}

    /**
     * Ejecuta una métrica de forma canónica, determinista, read-only y tenant-safe.
     */
    public function execute(
        string $metricKey,
        AnalyticsFilterSet $filterSet,
        ?string $periodKey = null,
        bool $compare = false,
        array $additionalFilters = [],
        bool $skipCache = false
    ): IntelligenceResult {
        // 1. Tenant Context Validation (FAIL-CLOSED)
        $tenantId = $filterSet->tenantId;
        if ($tenantId <= 0) {
            throw new HttpException(403, 'Contexto de tenant inválido o no suministrado.');
        }

        // 2. Metric Definition Lookup
        $definition = $this->registry->get($metricKey);

        // 3. Timezone Integrity Check (NO SILENT FALLBACK)
        if (!$filterSet->isTimezoneResolved || $filterSet->timezone === AnalyticsFilterSet::TIMEZONE_UNRESOLVED) {
            return new IntelligenceResult(
                metric: $definition->key,
                metricName: $definition->name,
                value: 0,
                unit: $definition->unit === 'currency' ? $filterSet->currency : $definition->unit,
                status: MetricDataStatus::INSUFFICIENT_DATA,
                period: ['timezone' => AnalyticsFilterSet::TIMEZONE_UNRESOLVED],
                tenantId: $tenantId,
                provenance: [
                    'source' => $definition->source,
                    'generated_at' => now()->toIso8601String(),
                ],
                message: 'Zona horaria del tenant no resuelta o inválida. Se prohíbe fallback silencioso a UTC.'
            );
        }

        // 4. Resolver TimePeriod
        $effectivePeriodKey = $periodKey ?? ($filterSet->dateFrom ? TimePeriod::CUSTOM_RANGE : TimePeriod::THIS_MONTH);
        $period = new TimePeriod(
            key: $effectivePeriodKey,
            timezone: $filterSet->timezone,
            dateFrom: $filterSet->dateFrom,
            dateTo: $filterSet->dateTo
        );

        // Combinar filtros autorizados
        $effectiveFilters = array_merge([
            'channel' => $filterSet->channel,
            'payment_method' => $filterSet->paymentMethod,
        ], $additionalFilters);
        $effectiveFilters = array_filter($effectiveFilters, fn($v) => $v !== null && $v !== '');

        // 5. Cache Check (Tenant-Isolated)
        $cachePeriodKey = $period->key . '_' . ($period->dateFrom ?? 'ALL') . '_' . ($period->dateTo ?? 'ALL');
        if (!$skipCache) {
            $cached = $this->cacheService->get($tenantId, $metricKey, $cachePeriodKey, $effectiveFilters);
            if ($cached !== null) {
                return new IntelligenceResult(
                    metric: $cached['metric'],
                    metricName: $cached['metric_name'],
                    value: $cached['value'],
                    unit: $cached['unit'],
                    status: $cached['status'],
                    period: $cached['period'],
                    tenantId: $cached['tenant_id'],
                    comparison: isset($cached['comparison']) ? new PeriodComparison(
                        $cached['comparison']['current_value'],
                        $cached['comparison']['previous_value'],
                        $cached['comparison']['previous_period'] ?? []
                    ) : null,
                    provenance: array_merge($cached['provenance'], ['cached' => true]),
                    message: $cached['message'] ?? null
                );
            }
        }

        // 6. Verificar si el tenant posee datos para la categoría de la métrica
        if ($definition->category === 'sales') {
            $totalTenantOrders = DB::table('orders')->where('tenant_id', $tenantId)->count();
            if ($totalTenantOrders === 0) {
                $unit = $definition->unit === 'currency' ? $filterSet->currency : $definition->unit;
                return new IntelligenceResult(
                    metric: $definition->key,
                    metricName: $definition->name,
                    value: is_array($definition->allowedFilters) && in_array('limit', $definition->allowedFilters) ? [] : 0,
                    unit: $unit,
                    status: MetricDataStatus::NO_DATA,
                    period: $period->toArray(),
                    tenantId: $tenantId,
                    provenance: [
                        'source' => $definition->source,
                        'calculator' => $definition->calculatorClass,
                        'generated_at' => now()->toIso8601String(),
                    ],
                    message: 'No existen registros de órdenes para este tenant.'
                );
            }
        } elseif ($definition->category === 'customers') {
            $hasData = DB::table('customers')->where('tenant_id', $tenantId)->exists()
                || DB::table('orders')->where('tenant_id', $tenantId)->exists();
            if (!$hasData) {
                $unit = $definition->unit === 'currency' ? $filterSet->currency : $definition->unit;
                return new IntelligenceResult(
                    metric: $definition->key,
                    metricName: $definition->name,
                    value: is_array($definition->allowedFilters) && in_array('limit', $definition->allowedFilters) ? [] : 0,
                    unit: $unit,
                    status: MetricDataStatus::NO_DATA,
                    period: $period->toArray(),
                    tenantId: $tenantId,
                    provenance: [
                        'source' => $definition->source,
                        'calculator' => $definition->calculatorClass,
                        'generated_at' => now()->toIso8601String(),
                    ],
                    message: 'No existen registros de clientes ni pedidos para este tenant.'
                );
            }
        } elseif (in_array($definition->category, ['products', 'categories', 'variants', 'inventory'])) {
            $hasData = DB::table('products')->where('tenant_id', $tenantId)->exists()
                || DB::table('inventory_stocks')->where('tenant_id', $tenantId)->exists()
                || DB::table('orders')->where('tenant_id', $tenantId)->exists();
            if (!$hasData) {
                $unit = $definition->unit === 'currency' ? $filterSet->currency : $definition->unit;
                return new IntelligenceResult(
                    metric: $definition->key,
                    metricName: $definition->name,
                    value: is_array($definition->allowedFilters) && in_array('limit', $definition->allowedFilters) ? [] : 0,
                    unit: $unit,
                    status: MetricDataStatus::NO_DATA,
                    period: $period->toArray(),
                    tenantId: $tenantId,
                    provenance: [
                        'source' => $definition->source,
                        'calculator' => $definition->calculatorClass,
                        'generated_at' => now()->toIso8601String(),
                    ],
                    message: 'No existen registros de catálogo ni inventario para este tenant.'
                );
            }
        }

        // 7. Instanciar Calculator vía Container
        $calculator = $this->container->make($definition->calculatorClass);

        // 8. Ejecutar Cálculo del Periodo Actual (READ-ONLY)
        $currentValue = $calculator->calculate($tenantId, $period, $effectiveFilters);

        // 9. Comparación con Periodo Previo (si solicitada)
        $comparison = null;
        if ($compare && is_numeric($currentValue)) {
            $previousPeriod = $period->getPreviousPeriod();
            if ($previousPeriod !== null) {
                $prevValue = $calculator->calculate($tenantId, $previousPeriod, $effectiveFilters);
                $comparison = new PeriodComparison(
                    currentValue: (float) $currentValue,
                    previousValue: (float) $prevValue,
                    previousPeriod: $previousPeriod->toArray()
                );
            }
        }

        $unit = $definition->unit === 'currency' ? $filterSet->currency : $definition->unit;

        $result = new IntelligenceResult(
            metric: $definition->key,
            metricName: $definition->name,
            value: $currentValue,
            unit: $unit,
            status: MetricDataStatus::KNOWN,
            period: $period->toArray(),
            tenantId: $tenantId,
            comparison: $comparison,
            provenance: [
                'metric_key' => $definition->key,
                'source' => $definition->source,
                'aggregation' => $definition->aggregation,
                'calculator' => $definition->calculatorClass,
                'time_dimension' => $definition->timeDimension,
                'timezone_policy' => $definition->timezonePolicy,
                'generated_at' => now()->toIso8601String(),
                'cached' => false,
            ]
        );

        // 10. Cache Put (Tenant-Isolated)
        if (!$skipCache) {
            $this->cacheService->put($tenantId, $metricKey, $cachePeriodKey, $effectiveFilters, $result->toArray());
        }

        return $result;
    }

    public function getRegistry(): MetricRegistry
    {
        return $this->registry;
    }
}
