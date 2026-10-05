<?php

namespace App\Modules\Orders\Services;

use App\Modules\Business\Repositories\BusinessSettingRepository;

class OrderCalculator
{
    public function __construct(
        private readonly BusinessSettingRepository $settingRepository
    ) {}

    public function calculate(int $tenantId, array $items, string $orderType = 'DELIVERY', float $discountAmount = 0.00): array
    {
        $subtotal = 0.00;
        $processedItems = [];

        foreach ($items as $item) {
            $basePrice = (float) ($item['base_price'] ?? 0.00);
            $quantity = (int) ($item['quantity'] ?? 1);
            $optionsModifiers = 0.00;
            $selectedOptionsSnapshot = [];

            foreach ($item['selected_options'] ?? [] as $opt) {
                $modifier = (float) ($opt['price_modifier'] ?? 0.00);
                $optionsModifiers += $modifier;
                $selectedOptionsSnapshot[] = [
                    'option_name' => $opt['option_name'] ?? '',
                    'value_name' => $opt['value_name'] ?? '',
                    'price_modifier' => $modifier,
                ];
            }

            $calculatedUnitPrice = $basePrice + $optionsModifiers;
            $itemSubtotal = $calculatedUnitPrice * $quantity;
            $subtotal += $itemSubtotal;

            $processedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'product_uuid' => $item['product_uuid'] ?? null,
                'product_name' => $item['product_name'] ?? 'Producto',
                'sku' => $item['sku'] ?? null,
                'unit_price' => $calculatedUnitPrice,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
                'options_snapshot' => [
                    'product_uuid' => $item['product_uuid'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'product_name' => $item['product_name'] ?? 'Producto',
                    'base_price' => $basePrice,
                    'selected_options' => $selectedOptionsSnapshot,
                    'calculated_unit_price' => $calculatedUnitPrice,
                ],
            ];
        }

        $deliveryFee = 0.00;
        if (strtoupper($orderType) === 'DELIVERY') {
            $settings = $this->settingRepository->getByTenantId($tenantId);
            if ($settings && $settings->delivery_enabled) {
                $deliveryFee = (float) $settings->delivery_fee;
            }
        }

        $totalAmount = max(0.00, ($subtotal + $deliveryFee) - $discountAmount);

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'items' => $processedItems,
        ];
    }
}
