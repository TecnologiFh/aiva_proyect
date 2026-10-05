<?php

namespace App\Modules\AI\Services\OperationalTools;

use Illuminate\Support\Facades\DB;

class GetDeliveryInfoTool
{
    /**
     * Consultar información oficial de delivery y zonas de envío calculadas por el Core.
     */
    public function execute(int $tenantId, ?string $zoneName = null): array
    {
        $query = DB::table('delivery_zones')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        if (!empty($zoneName)) {
            $query->where('zone_name', 'LIKE', '%' . trim($zoneName) . '%');
        }

        $zones = $query->get();

        if ($zones->isEmpty()) {
            return [
                'tenant_id' => $tenantId,
                'found' => false,
                'message' => 'No se encontraron zonas de delivery específicas registradas.',
                'available_zones' => DB::table('delivery_zones')->where('tenant_id', $tenantId)->pluck('zone_name')->toArray(),
            ];
        }

        $zoneList = [];
        foreach ($zones as $z) {
            $zoneList[] = [
                'zone_name' => $z->zone_name,
                'delivery_fee' => (float)$z->delivery_fee,
                'estimated_time_mins' => (int)$z->estimated_time_mins,
                'min_order_amount' => (float)$z->min_order_amount,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'found' => true,
            'zones' => $zoneList,
        ];
    }
}
