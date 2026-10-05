<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\BusinessProfile;

class BusinessProfileService
{
    /**
     * Obtener o crear el Perfil de Negocio para un Tenant específico.
     */
    public function getProfileForTenant(int $tenantId): BusinessProfile
    {
        return BusinessProfile::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'commercial_name' => 'Comercio Tenant #' . $tenantId,
                'business_category' => 'General',
                'description' => 'Perfil empresarial registrado en AIVA Business Operating System.',
                'primary_color' => '#4F46E5',
                'secondary_color' => '#10B981',
                'avg_preparation_mins' => 30,
                'avg_delivery_mins' => 45,
                'min_order_amount' => 0.00,
                'business_policies' => [
                    'returns' => 'Se aceptan cambios dentro de las 24 horas con comprobante.',
                    'cancellations' => 'Cancelación sin costo antes de iniciar la preparación.',
                    'reservations' => 'Requiere confirmación previa.',
                ]
            ]
        );
    }

    /**
     * Actualizar la identidad o políticas del negocio.
     */
    public function updateProfile(int $tenantId, array $data): BusinessProfile
    {
        $profile = $this->getProfileForTenant($tenantId);
        $profile->update($data);
        return $profile->fresh();
    }
}
