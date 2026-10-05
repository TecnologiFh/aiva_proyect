<?php

namespace App\Modules\AI\Services\OperationalTools;

class OperationalToolGateway
{
    protected GetBusinessInfoTool $businessInfoTool;
    protected GetDeliveryInfoTool $deliveryInfoTool;
    protected GetOrderStatusTool $orderStatusTool;

    public function __construct()
    {
        $this->businessInfoTool = new GetBusinessInfoTool();
        $this->deliveryInfoTool = new GetDeliveryInfoTool();
        $this->orderStatusTool = new GetOrderStatusTool();
    }

    /**
     * Ejecutar una herramienta de IA autorizada con validación estricta del Core.
     */
    public function dispatchTool(int $tenantId, string $toolName, array $params = []): array
    {
        switch ($toolName) {
            case 'get_business_info':
                return $this->businessInfoTool->execute($tenantId);

            case 'get_delivery_info':
                $zoneName = $params['zone_name'] ?? null;
                return $this->deliveryInfoTool->execute($tenantId, $zoneName);

            case 'get_order_status':
                $orderNumber = $params['order_number'] ?? null;
                $phone = $params['customer_phone'] ?? null;
                return $this->orderStatusTool->execute($tenantId, $orderNumber, $phone);

            default:
                return [
                    'tenant_id' => $tenantId,
                    'error' => "Herramienta no autorizada o desconocida: {$toolName}",
                ];
        }
    }
}
