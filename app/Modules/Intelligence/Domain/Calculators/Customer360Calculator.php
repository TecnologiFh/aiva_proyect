<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Core\Identity\PhoneNormalizer;
use App\Modules\CRM\Models\Customer;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use InvalidArgumentException;

class Customer360Calculator implements MetricCalculatorInterface
{
    public function __construct(
        private readonly CustomersRfmCalculator $rfmCalculator
    ) {}

    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        if (empty($filters['customer_id'])) {
            throw new InvalidArgumentException("Filtro 'customer_id' es obligatorio para la vista Customer 360.");
        }

        $customerId = (int) $filters['customer_id'];

        // 1. Tenant Isolation & Verification (FAIL-CLOSED)
        $customer = Customer::where('tenant_id', $tenantId)->find($customerId);
        if (!$customer) {
            throw new NotFoundHttpException("Cliente #{$customerId} no encontrado o no pertenece a este tenant.");
        }

        // 2. Query Orders canonically (READ-ONLY)
        $ordersQuery = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

        PhoneNormalizer::applyCanonicalQuery($ordersQuery, $tenantId, $customer->phone);

        $stats = $ordersQuery->selectRaw("
            COUNT(id) as orders_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            MIN(created_at) as first_order_at,
            MAX(created_at) as last_order_at
        ")->first();

        $ordersCount = (int) ($stats->orders_count ?? 0);
        $totalRevenue = round((float) ($stats->total_revenue ?? 0.00), 2);
        $firstOrderAt = $stats->first_order_at ?? null;
        $lastOrderAt = $stats->last_order_at ?? null;

        $aov = $ordersCount > 0 ? round($totalRevenue / $ordersCount, 2) : 0.00;
        $daysSinceLastOrder = $lastOrderAt ? (int) Carbon::parse($lastOrderAt)->diffInDays(now('UTC')) : null;

        // 3. RFM Metrics (Policy v1.0)
        $rfm = $this->rfmCalculator->calculate($tenantId, $period, ['customer_id' => $customerId]);

        return [
            'customer' => [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'tenant_id' => $customer->tenant_id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'customer_phone_canonical' => $customer->customer_phone_canonical,
                'email' => $customer->email,
                'preferred_channel' => $customer->preferred_channel,
                'tag' => $customer->tag,
                'created_at' => $customer->created_at ? Carbon::parse($customer->created_at)->toIso8601String() : null,
            ],
            'orders_count' => $ordersCount,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $aov,
            'first_order_at' => $firstOrderAt ? Carbon::parse($firstOrderAt)->toIso8601String() : null,
            'last_order_at' => $lastOrderAt ? Carbon::parse($lastOrderAt)->toIso8601String() : null,
            'days_since_last_order' => $daysSinceLastOrder,
            'customer_revenue_to_date' => $totalRevenue, // Section 21: descriptive revenue to date
            'rfm' => [
                'score' => $rfm['rfm_score'] ?? '111',
                'segment' => $rfm['rfm_segment'] ?? 'NO_ACTIVITY',
                'recency_days' => $rfm['metrics']['recency_days'] ?? 999,
                'frequency_count' => $rfm['metrics']['frequency_count'] ?? 0,
                'monetary_total' => $rfm['metrics']['monetary_total'] ?? '0.00',
            ],
        ];
    }
}
