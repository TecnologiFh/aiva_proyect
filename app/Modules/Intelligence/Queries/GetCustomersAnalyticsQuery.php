<?php

namespace App\Modules\Intelligence\Queries;

use App\Modules\Intelligence\DTOs\AnalyticsFilterSet;
use App\Modules\Intelligence\DTOs\CustomersAnalyticsDTO;
use App\Modules\Intelligence\DTOs\MetricProvenanceDTO;
use App\Modules\Intelligence\Support\MetricDataStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetCustomersAnalyticsQuery
{
    /**
     * Ejecuta la consulta analítica de clientes e identidades compradoras.
     * Estrictamente READ-ONLY, tenant-scoped, determinista y auditable.
     */
    public function execute(AnalyticsFilterSet $filterSet): CustomersAnalyticsDTO
    {
        $tenantId = $filterSet->tenantId;
        $dateFrom = $filterSet->dateFrom;
        $dateTo = $filterSet->dateTo;
        $timezone = $filterSet->timezone;
        $currency = $filterSet->currency;

        // 1. Timezone Guard (Fail-Closed, zero silent fallbacks)
        if (!$filterSet->isTimezoneResolved || $timezone === AnalyticsFilterSet::TIMEZONE_UNRESOLVED) {
            return new CustomersAnalyticsDTO(
                status: MetricDataStatus::INSUFFICIENT_DATA,
                summary: [
                    'registered_customers' => 0,
                    'buyer_identities' => 0,
                    'buyer_identities_with_non_cancelled_orders' => 0,
                    'repeat_buyer_identities_by_non_cancelled_orders' => 0,
                    'customer_order_value' => 0.00,
                    'customer_product_revenue' => 0.00,
                ],
                attribution: [
                    'status' => CustomersAnalyticsDTO::ATTRIBUTION_PARTIAL,
                    'total_orders_in_scope' => 0,
                    'orders_with_valid_canonical_phone' => 0,
                    'orders_without_valid_canonical_phone' => 0,
                    'coverage_percentage' => 0.00,
                ],
                byChannel: [
                    'buyer_identities_by_order_channel' => [],
                    'registered_customers_by_preferred_channel' => [],
                ],
                topBuyerIdentities: [],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'customers_analytics',
                    sourceOfTruth: 'tenants.timezone',
                    calculationSource: 'GetCustomersAnalyticsQuery (TIMEZONE_GUARD)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: AnalyticsFilterSet::TIMEZONE_UNRESOLVED,
                    currency: $currency
                ),
                message: 'Zona horaria del tenant no disponible o inválida (TIMEZONE_UNRESOLVED). Se prohíbe fallback silencioso.'
            );
        }

        // 2. C31-01 — registered_customers (Authority: customers table, CRM row count)
        $registeredCustomers = (int) DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->count();

        // 3. Orders scope query
        $ordersScopeQuery = DB::table('orders')->where('tenant_id', $tenantId);

        $startUtc = $filterSet->getStartDateTimeUtcString();
        $endUtc = $filterSet->getEndDateTimeUtcString();

        if ($startUtc) {
            $ordersScopeQuery->where('created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $ordersScopeQuery->where('created_at', '<=', $endUtc);
        }
        if ($filterSet->channel) {
            $ordersScopeQuery->where('channel', $filterSet->channel);
        }
        if ($filterSet->paymentMethod) {
            $ordersScopeQuery->where('payment_method', $filterSet->paymentMethod);
        }

        // 4. Identity Attribution Coverage
        $totalOrdersInScope = (int) (clone $ordersScopeQuery)->count();

        $validOrdersInScope = (int) (clone $ordersScopeQuery)
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->count();

        $ordersWithoutCanonical = $totalOrdersInScope - $validOrdersInScope;

        $attributionStatus = ($totalOrdersInScope > 0 && $ordersWithoutCanonical === 0)
            ? CustomersAnalyticsDTO::ATTRIBUTION_COMPLETE
            : CustomersAnalyticsDTO::ATTRIBUTION_PARTIAL;

        $coveragePercentage = ($totalOrdersInScope > 0)
            ? round(($validOrdersInScope / $totalOrdersInScope) * 100, 2)
            : 100.00;

        // Si no existen clientes registrados ni órdenes en el scope
        if ($registeredCustomers === 0 && $totalOrdersInScope === 0) {
            return new CustomersAnalyticsDTO(
                status: MetricDataStatus::NO_DATA,
                summary: [
                    'registered_customers' => 0,
                    'buyer_identities' => 0,
                    'buyer_identities_with_non_cancelled_orders' => 0,
                    'repeat_buyer_identities_by_non_cancelled_orders' => 0,
                    'customer_order_value' => 0.00,
                    'customer_product_revenue' => 0.00,
                ],
                attribution: [
                    'status' => CustomersAnalyticsDTO::ATTRIBUTION_COMPLETE,
                    'total_orders_in_scope' => 0,
                    'orders_with_valid_canonical_phone' => 0,
                    'orders_without_valid_canonical_phone' => 0,
                    'coverage_percentage' => 100.00,
                ],
                byChannel: [
                    'buyer_identities_by_order_channel' => [],
                    'registered_customers_by_preferred_channel' => [],
                ],
                topBuyerIdentities: [],
                period: [
                    'from' => $dateFrom ?? 'ALL_TIME',
                    'to' => $dateTo ?? 'ALL_TIME',
                ],
                timezone: $timezone,
                currency: $currency,
                provenance: new MetricProvenanceDTO(
                    metricName: 'customers_analytics',
                    sourceOfTruth: 'customers + orders + order_items',
                    calculationSource: 'GetCustomersAnalyticsQuery (DIRECT_QUERY)',
                    period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                    timezone: $timezone,
                    currency: $currency
                ),
                message: 'No existen registros de clientes ni pedidos para este tenant en el período seleccionado.'
            );
        }

        // 5. C31-02 — buyer_identities (Distinct canonical phone in orders)
        $buyerIdentities = (int) (clone $ordersScopeQuery)
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->distinct()
            ->count('customer_phone_canonical');

        // 6. C31-03 — buyer_identities_with_non_cancelled_orders
        $buyerIdentitiesNonCancelled = (int) (clone $ordersScopeQuery)
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->where('status', '!=', 'CANCELLED')
            ->distinct()
            ->count('customer_phone_canonical');

        // 7. C31-04 — repeat_buyer_identities_by_non_cancelled_orders
        $repeatIdentitiesList = (clone $ordersScopeQuery)
            ->select('customer_phone_canonical')
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->where('status', '!=', 'CANCELLED')
            ->groupBy('customer_phone_canonical')
            ->havingRaw('COUNT(id) >= 2')
            ->pluck('customer_phone_canonical');

        $repeatBuyerIdentities = $repeatIdentitiesList->count();

        // 8. C31-05 — customer_order_value (Semantic: COMMERCIAL_ORDER_VALUE)
        $customerOrderValueSum = (float) ((clone $ordersScopeQuery)
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->where('status', '!=', 'CANCELLED')
            ->sum('total_amount') ?? 0.00);

        // 9. C31-06 — customer_product_revenue (Semantic: PRODUCT_LINE_BILLING from order_items.subtotal)
        $productRevenueQuery = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.tenant_id', $tenantId)
            ->where('oi.tenant_id', $tenantId)
            ->where('o.status', '!=', 'CANCELLED')
            ->whereNotNull('o.customer_phone_canonical')
            ->where('o.customer_phone_canonical', '!=', '');

        if ($startUtc) {
            $productRevenueQuery->where('o.created_at', '>=', $startUtc);
        }
        if ($endUtc) {
            $productRevenueQuery->where('o.created_at', '<=', $endUtc);
        }
        if ($filterSet->channel) {
            $productRevenueQuery->where('o.channel', $filterSet->channel);
        }
        if ($filterSet->paymentMethod) {
            $productRevenueQuery->where('o.payment_method', $filterSet->paymentMethod);
        }

        $customerProductRevenue = (float) ($productRevenueQuery->sum('oi.subtotal') ?? 0.00);

        // 10. Channel Breakdowns (Strict: UNKNOWN for null/whitespace)
        $rawOrderChannels = (clone $ordersScopeQuery)
            ->selectRaw("channel, COUNT(DISTINCT customer_phone_canonical) as count")
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->groupBy('channel')
            ->get();

        $buyerIdentitiesByChannel = [];
        foreach ($rawOrderChannels as $row) {
            $ch = trim((string) $row->channel);
            $key = ($ch === '') ? 'UNKNOWN' : $ch;
            $buyerIdentitiesByChannel[$key] = ($buyerIdentitiesByChannel[$key] ?? 0) + (int) $row->count;
        }

        $rawCrmChannels = DB::table('customers')
            ->selectRaw("preferred_channel, COUNT(id) as count")
            ->where('tenant_id', $tenantId)
            ->groupBy('preferred_channel')
            ->get();

        $registeredCustomersByChannel = [];
        foreach ($rawCrmChannels as $row) {
            $ch = trim((string) $row->preferred_channel);
            $key = ($ch === '') ? 'UNKNOWN' : $ch;
            $registeredCustomersByChannel[$key] = ($registeredCustomersByChannel[$key] ?? 0) + (int) $row->count;
        }

        // 11. Top Buyer Identities
        $topIdentitiesRaw = (clone $ordersScopeQuery)
            ->selectRaw("
                customer_phone_canonical,
                COUNT(id) as orders_count,
                COALESCE(SUM(total_amount), 0) as order_value,
                MIN(created_at) as first_order_at,
                MAX(created_at) as last_order_at
            ")
            ->whereNotNull('customer_phone_canonical')
            ->where('customer_phone_canonical', '!=', '')
            ->where('status', '!=', 'CANCELLED')
            ->groupBy('customer_phone_canonical')
            ->orderByDesc('order_value')
            ->limit(10)
            ->get();

        $topBuyerIdentities = [];
        foreach ($topIdentitiesRaw as $row) {
            $phone = (string) $row->customer_phone_canonical;

            // Product revenue específico para esta identidad
            $itemSubtotal = (float) DB::table('orders as o')
                ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
                ->where('o.tenant_id', $tenantId)
                ->where('oi.tenant_id', $tenantId)
                ->where('o.status', '!=', 'CANCELLED')
                ->where('o.customer_phone_canonical', $phone)
                ->when($startUtc, fn($q) => $q->where('o.created_at', '>=', $startUtc))
                ->when($endUtc, fn($q) => $q->where('o.created_at', '<=', $endUtc))
                ->sum('oi.subtotal');

            $firstAt = $row->first_order_at ? Carbon::parse($row->first_order_at, 'UTC')->setTimezone($timezone)->toIso8601String() : null;
            $lastAt = $row->last_order_at ? Carbon::parse($row->last_order_at, 'UTC')->setTimezone($timezone)->toIso8601String() : null;

            $topBuyerIdentities[] = [
                'customer_phone_canonical' => $phone,
                'orders_count' => (int) $row->orders_count,
                'order_value' => round((float) $row->order_value, 2),
                'product_revenue' => round($itemSubtotal, 2),
                'first_order_at' => $firstAt,
                'last_order_at' => $lastAt,
            ];
        }

        return new CustomersAnalyticsDTO(
            status: MetricDataStatus::KNOWN,
            summary: [
                'registered_customers' => $registeredCustomers,
                'buyer_identities' => $buyerIdentities,
                'buyer_identities_with_non_cancelled_orders' => $buyerIdentitiesNonCancelled,
                'repeat_buyer_identities_by_non_cancelled_orders' => $repeatBuyerIdentities,
                'customer_order_value' => round($customerOrderValueSum, 2),
                'customer_product_revenue' => round($customerProductRevenue, 2),
            ],
            attribution: [
                'status' => $attributionStatus,
                'total_orders_in_scope' => $totalOrdersInScope,
                'orders_with_valid_canonical_phone' => $validOrdersInScope,
                'orders_without_valid_canonical_phone' => $ordersWithoutCanonical,
                'coverage_percentage' => $coveragePercentage,
            ],
            byChannel: [
                'buyer_identities_by_order_channel' => $buyerIdentitiesByChannel,
                'registered_customers_by_preferred_channel' => $registeredCustomersByChannel,
            ],
            topBuyerIdentities: $topBuyerIdentities,
            period: [
                'from' => $dateFrom ?? 'ALL_TIME',
                'to' => $dateTo ?? 'ALL_TIME',
            ],
            timezone: $timezone,
            currency: $currency,
            provenance: new MetricProvenanceDTO(
                metricName: 'customers_analytics',
                sourceOfTruth: 'customers + orders.customer_phone_canonical + order_items.subtotal',
                calculationSource: 'GetCustomersAnalyticsQuery (DIRECT_QUERY)',
                period: ['from' => $dateFrom ?? 'ALL_TIME', 'to' => $dateTo ?? 'ALL_TIME'],
                timezone: $timezone,
                currency: $currency
            ),
            message: ($ordersWithoutCanonical > 0)
                ? "Atribución parcial: existen {$ordersWithoutCanonical} pedidos sin teléfono canónico en el período (IDENTITY_ATTRIBUTION_PARTIAL)."
                : null
        );
    }
}
