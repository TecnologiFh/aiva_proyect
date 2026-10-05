<?php

namespace App\Modules\Intelligence\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\Queries\GetCustomersAnalyticsQuery;
use App\Modules\Intelligence\Queries\GetOrdersOperationsQuery;
use App\Modules\Intelligence\Queries\GetOrdersStatusQuery;
use App\Modules\Intelligence\Queries\GetOrdersSummaryQuery;
use App\Modules\Intelligence\Queries\GetProductsAnalyticsQuery;
use App\Modules\Intelligence\Queries\GetSalesAnalyticsQuery;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntelligenceController extends Controller
{
    public function sales(Request $request, GetSalesAnalyticsQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Analítica de ventas calculada exitosamente por el módulo Intelligence'
        );
    }

    public function orders(Request $request, GetOrdersSummaryQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result,
            message: 'Resumen de pedidos calculado exitosamente por el módulo Intelligence'
        );
    }

    public function ordersStatus(Request $request, GetOrdersStatusQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result,
            message: 'Distribución de estados calculada exitosamente por el módulo Intelligence'
        );
    }

    public function operations(Request $request, GetOrdersOperationsQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Métricas de operaciones y timeline calculadas exitosamente por el módulo Intelligence'
        );
    }

    public function customers(Request $request, GetCustomersAnalyticsQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Analítica de clientes e identidades calculada exitosamente por el módulo Intelligence'
        );
    }

    public function products(Request $request, GetProductsAnalyticsQuery $query): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $query->execute($filterSet);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Analítica de productos y catálogo calculada exitosamente por el módulo Intelligence'
        );
    }

    /**
     * Catálogo oficial de métricas registradas en Intelligence Foundation.
     */
    public function metrics(Request $request, \App\Modules\Intelligence\Domain\Registry\MetricRegistry $registry): JsonResponse
    {
        $category = $request->query('category');
        $metrics = $category ? $registry->getByCategory($category) : $registry->all();

        $data = array_map(fn($m) => $m->toArray(), array_values($metrics));

        return ApiResponse::success(
            data: $data,
            message: 'Catálogo de métricas registradas de Intelligence recuperado exitosamente.'
        );
    }

    /**
     * Ejecutar una métrica canónica por clave mediante el MetricEngine.
     */
    public function queryMetric(Request $request, string $metricKey, \App\Modules\Intelligence\Domain\Engine\MetricEngine $engine): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $periodKey = $request->query('period');
        $compare = $request->boolean('compare', false);
        $skipCache = $request->boolean('skip_cache', false);

        $additionalFilters = [
            'limit' => $request->query('limit'),
            'by' => $request->query('by'),
            'type' => $request->query('type'),
            'product_id' => $request->query('product_id'),
            'category_id' => $request->query('category_id'),
            'customer_id' => $request->query('customer_id'),
            'tag' => $request->query('tag'),
            'status' => $request->query('status'),
            'fulfillment_status' => $request->query('fulfillment_status'),
            'order_type' => $request->query('order_type'),
            'cancelled_by' => $request->query('cancelled_by'),
            'cancellation_channel' => $request->query('cancellation_channel'),
            'preferred_channel' => $request->query('preferred_channel'),
            'policy_version' => $request->query('policy_version'),
            'refund_method' => $request->query('refund_method'),
        ];

        $result = $engine->execute(
            metricKey: $metricKey,
            filterSet: $filterSet,
            periodKey: $periodKey,
            compare: $compare,
            additionalFilters: $additionalFilters,
            skipCache: $skipCache
        );

        return ApiResponse::success(
            data: $result->toArray(),
            message: "Métrica '{$metricKey}' calculada exitosamente por MetricEngine."
        );
    }

    /**
     * Consulta analítica del perfil consolidado Customer 360 (READ-ONLY).
     */
    public function customer360(int $customerId, Request $request, \App\Modules\Intelligence\Domain\Engine\MetricEngine $engine): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->query('start_date') ?: $request->query('date_from'),
            dateTo: $request->query('end_date') ?: $request->query('date_to'),
            timezone: $request->query('timezone'),
            currency: $request->query('currency'),
            channel: $request->query('channel'),
            paymentMethod: $request->query('payment_method')
        );

        $result = $engine->execute(
            metricKey: 'customers.360',
            filterSet: $filterSet,
            additionalFilters: ['customer_id' => $customerId],
            skipCache: $request->boolean('skip_cache', false)
        );

        return ApiResponse::success(
            data: $result->toArray(),
            message: "Perfil Customer 360 calculado exitosamente por MetricEngine."
        );
    }

    /**
     * Consulta por lote (Batch) de múltiples métricas.
     */
    public function batchQuery(Request $request, \App\Modules\Intelligence\Domain\Engine\MetricEngine $engine): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $filterSet = new AnalyticsFilterSet(
            tenantId: $tenantId,
            dateFrom: $request->input('start_date') ?: $request->input('date_from'),
            dateTo: $request->input('end_date') ?: $request->input('date_to'),
            timezone: $request->input('timezone'),
            currency: $request->input('currency'),
            channel: $request->input('channel'),
            paymentMethod: $request->input('payment_method')
        );

        $metricKeys = (array) $request->input('metrics', []);
        $periodKey = $request->input('period');
        $compare = (bool) $request->input('compare', false);
        $skipCache = (bool) $request->input('skip_cache', false);

        $results = [];
        foreach ($metricKeys as $key) {
            if ($engine->getRegistry()->has($key)) {
                $res = $engine->execute(
                    metricKey: $key,
                    filterSet: $filterSet,
                    periodKey: $periodKey,
                    compare: $compare,
                    skipCache: $skipCache
                );
                $results[$key] = $res->toArray();
            }
        }

        return ApiResponse::success(
            data: $results,
            message: 'Consulta por lote de métricas ejecutada exitosamente por MetricEngine.'
        );
    }
}
