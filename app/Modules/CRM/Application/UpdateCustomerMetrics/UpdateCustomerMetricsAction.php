<?php

namespace App\Modules\CRM\Application\UpdateCustomerMetrics;

use App\Core\Enums\CustomerStage;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\CustomerProfile;
use App\Modules\CRM\Services\CustomerBrainCompiler;
use Illuminate\Support\Facades\DB;

class UpdateCustomerMetricsAction
{
    public function __construct(
        private readonly CustomerBrainCompiler $brainCompiler
    ) {}

    public function execute(UpdateCustomerMetricsInput $input): Customer
    {
        $customer = Customer::findOrFail($input->customerId);

        DB::transaction(function () use ($customer, $input) {
            $profile = CustomerProfile::firstOrCreate(
                ['tenant_id' => $input->tenantId, 'customer_id' => $customer->id]
            );

            $newOrdersCount = $profile->total_orders_count + 1;
            $newTotalSpent = $profile->total_spent + $input->orderAmount;
            $newAvgTicket = round($newTotalSpent / $newOrdersCount, 2);

            // Cálculo dinámico de Customer Score (0 a 100)
            $score = 50;
            if ($newOrdersCount >= 10) $score += 25;
            elseif ($newOrdersCount >= 3) $score += 15;
            if ($newAvgTicket >= 100) $score += 20;
            elseif ($newAvgTicket >= 50) $score += 10;
            $score = min(100, $score);

            // Determinación de Customer Stage
            $stage = CustomerStage::ACTIVE->value;
            if ($newTotalSpent >= 500.00 || $newOrdersCount >= 10) {
                $stage = CustomerStage::VIP->value;
            } elseif ($newOrdersCount >= 3) {
                $stage = CustomerStage::LOYAL->value;
            }

            $profile->update([
                'total_orders_count' => $newOrdersCount,
                'total_spent' => $newTotalSpent,
                'average_ticket' => $newAvgTicket,
                'clv' => $newTotalSpent,
                'customer_score' => $score,
                'orders_30d' => $profile->orders_30d + 1,
                'orders_90d' => $profile->orders_90d + 1,
                'preferred_payment' => $input->paymentMethod ?? $profile->preferred_payment,
                'preferred_delivery' => $input->deliveryType ?? $profile->preferred_delivery,
                'last_order_at' => now(),
            ]);

            $customer->update(['customer_stage' => $stage]);
        });

        // Recompilar snapshot del Customer Brain en Redis
        $this->brainCompiler->compile($customer->refresh());

        return $customer;
    }
}
