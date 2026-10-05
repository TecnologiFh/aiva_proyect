<?php

namespace App\Modules\AI\Services\OperationalTools;

use App\Modules\Business\Services\BusinessProfileService;

class GetBusinessInfoTool
{
    protected BusinessProfileService $profileService;

    public function __construct(?BusinessProfileService $profileService = null)
    {
        $this->profileService = $profileService ?? new BusinessProfileService();
    }

    /**
     * Ejecutar la consulta segura del perfil e información comercial del negocio.
     */
    public function execute(int $tenantId): array
    {
        $profile = $this->profileService->getProfileForTenant($tenantId);
        $sources = \App\Modules\Business\Models\BusinessSource::where('tenant_id', $tenantId)->get();
        $resources = [];
        foreach ($sources as $s) {
            $resources[] = [
                'type' => $s->source_type,
                'title' => $s->title,
                'url' => $s->external_url ?? url($s->file_path ?? ''),
                'usage_instruction' => $s->source_type === 'WEB_URL' 
                    ? 'Enviar este link al cliente cuando pida ver el catálogo visual interactivo o tienda online.'
                    : ($s->source_type === 'DOCUMENT_PDF' ? 'Adjuntar o enviar este PDF cuando el cliente solicite la carta o menú completo.' : 'Enviar esta foto referencial.')
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'business_name' => $profile->commercial_name,
            'category' => $profile->business_category,
            'description' => $profile->description,
            'contact' => [
                'phone' => $profile->phone,
                'email' => $profile->email,
                'address' => $profile->address,
                'website' => $profile->website_url,
            ],
            'opening_hours' => $profile->opening_hours,
            'non_working_days' => $profile->non_working_days,
            'policies' => $profile->business_policies,
            'conversational_media_resources' => $resources,
        ];
    }
}
