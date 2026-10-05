<?php

namespace App\Modules\AI\Services\OperationalTools;

use App\Modules\CRM\Services\Customer360Service;

class GetCustomerHistoryTool
{
    protected Customer360Service $crmService;

    public function __construct()
    {
        $this->crmService = new Customer360Service();
    }

    /**
     * Consultar el perfil 360 del cliente por teléfono para atención personalizada en WhatsApp.
     */
    public function execute(int $tenantId, string $phone): array
    {
        $customer = $this->crmService->getOrCreateCustomer($tenantId, $phone);
        $profile = $this->crmService->getCustomer360Profile($tenantId, $customer->id);

        return [
            'tenant_id' => $tenantId,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'loyalty_tag' => $customer->tag,
            'total_orders' => $customer->total_orders_count,
            'total_spent' => (float)$customer->total_spent_amount,
            'preferred_channel' => $customer->preferred_channel,
            'is_vip' => $customer->tag === 'VIP',
            'greeting_suggestion' => $customer->tag === 'VIP' 
                ? "¡Hola {$customer->name}! Qué alegría tenerte de vuelta como nuestro cliente VIP."
                : "¡Hola {$customer->name}! Bienvenido nuevamente a AIVA.",
        ];
    }
}
