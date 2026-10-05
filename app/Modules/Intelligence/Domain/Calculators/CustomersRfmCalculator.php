<?php

namespace App\Modules\Intelligence\Domain\Calculators;

use App\Modules\Core\Identity\PhoneNormalizer;
use App\Modules\CRM\Models\Customer;
use App\Modules\Intelligence\Domain\Values\TimePeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomersRfmCalculator implements MetricCalculatorInterface
{
    /**
     * Calcula puntuación RFM (Recency, Frequency, Monetary) y segmentación determinista (Policy v1.0).
     */
    public function calculate(int $tenantId, TimePeriod $period, array $filters = []): array
    {
        $policyVersion = $filters['policy_version'] ?? 'v1.0';

        // 1. Si se solicita para un cliente específico (Customer 360 RFM)
        if (!empty($filters['customer_id'])) {
            return $this->calculateForSingleCustomer($tenantId, (int) $filters['customer_id'], $policyVersion);
        }

        // 2. Si es para el tenant completo, generar distribución de segmentos RFM
        return $this->calculateTenantDistribution($tenantId, $policyVersion);
    }

    private function calculateForSingleCustomer(int $tenantId, int $customerId, string $policyVersion): array
    {
        $customer = Customer::where('tenant_id', $tenantId)->find($customerId);
        if (!$customer) {
            throw new NotFoundHttpException("Cliente con ID {$customerId} no encontrado para este tenant.");
        }

        $ordersQuery = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'CANCELLED');

        PhoneNormalizer::applyCanonicalQuery($ordersQuery, $tenantId, $customer->phone);

        $stats = $ordersQuery->selectRaw("
            COUNT(id) as frequency_count,
            COALESCE(SUM(total_amount), 0) as monetary_total,
            MAX(created_at) as last_order_at
        ")->first();

        $frequency = (int) ($stats->frequency_count ?? 0);
        $monetary = (float) ($stats->monetary_total ?? 0.00);
        $lastOrderAt = $stats->last_order_at ?? null;

        $recencyDays = $lastOrderAt ? (int) now('UTC')->diffInDays(Carbon::parse($lastOrderAt)) : 999;

        if ($frequency === 0 && $monetary === 0.00 && $lastOrderAt === null) {
            return [
                'status' => 'RFM_CALCULATED_NO_ACTIVITY',
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'metrics' => [
                    'recency_days' => 999,
                    'frequency_count' => 0,
                    'monetary_total' => '0.00',
                ],
                'rfm_score' => '111',
                'rfm_segment' => 'NO_ACTIVITY',
                'governance' => [
                    'rfm_policy_version' => $policyVersion,
                    'no_ai_inference' => true,
                    'gate_id' => 'RFM-POLICY-PROVENANCE-GATE-01',
                    'calculated_at' => now('UTC')->toIso8601String(),
                ]
            ];
        }

        $rScore = ($recencyDays <= 30) ? 5 : (($recencyDays <= 60) ? 4 : (($recencyDays <= 90) ? 3 : (($recencyDays <= 180) ? 2 : 1)));
        $fScore = ($frequency >= 10) ? 5 : (($frequency >= 5) ? 4 : (($frequency >= 3) ? 3 : (($frequency >= 2) ? 2 : 1)));
        $mScore = ($monetary >= 1000) ? 5 : (($monetary >= 500) ? 4 : (($monetary >= 200) ? 3 : (($monetary >= 50) ? 2 : 1)));

        $rfmScore = "{$rScore}{$fScore}{$mScore}";

        if ($rScore >= 4 && $fScore >= 4 && $mScore >= 4) {
            $segment = 'CHAMPION';
        } elseif ($rScore >= 3 && $fScore >= 3) {
            $segment = 'LOYAL_CUSTOMER';
        } elseif ($rScore >= 4 && $fScore == 1) {
            $segment = 'NEW_CUSTOMER';
        } elseif ($rScore <= 2 && $fScore >= 3) {
            $segment = 'AT_RISK';
        } elseif ($rScore == 1) {
            $segment = 'HIBERNATING';
        } else {
            $segment = 'POTENTIAL_LOYALIST';
        }

        return [
            'status' => 'RFM_CALCULATED',
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'metrics' => [
                'recency_days' => $recencyDays,
                'frequency_count' => $frequency,
                'monetary_total' => number_format($monetary, 2, '.', ''),
            ],
            'rfm_score' => $rfmScore,
            'rfm_segment' => $segment,
            'governance' => [
                'rfm_policy_version' => $policyVersion,
                'no_ai_inference' => true,
                'gate_id' => 'RFM-POLICY-PROVENANCE-GATE-01',
                'calculated_at' => now('UTC')->toIso8601String(),
            ]
        ];
    }

    private function calculateTenantDistribution(int $tenantId, string $policyVersion): array
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();

        $distribution = [
            'CHAMPION' => 0,
            'LOYAL_CUSTOMER' => 0,
            'NEW_CUSTOMER' => 0,
            'AT_RISK' => 0,
            'HIBERNATING' => 0,
            'POTENTIAL_LOYALIST' => 0,
            'NO_ACTIVITY' => 0,
        ];

        foreach ($customers as $customer) {
            $res = $this->calculateForSingleCustomer($tenantId, $customer->id, $policyVersion);
            $seg = $res['rfm_segment'] ?? 'NO_ACTIVITY';
            if (isset($distribution[$seg])) {
                $distribution[$seg]++;
            } else {
                $distribution[$seg] = 1;
            }
        }

        return [
            'tenant_id' => $tenantId,
            'total_customers' => $customers->count(),
            'distribution' => $distribution,
            'policy_version' => $policyVersion,
        ];
    }
}
