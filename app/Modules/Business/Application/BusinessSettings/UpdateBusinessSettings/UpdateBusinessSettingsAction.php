<?php

namespace App\Modules\Business\Application\BusinessSettings\UpdateBusinessSettings;

use App\Core\Contracts\EventBus\EventBusInterface;
use App\Core\Events\BusinessSettingsUpdated;
use App\Core\Services\AuditService;
use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Business\Services\BusinessKnowledgeCompiler;

class UpdateBusinessSettingsAction
{
    public function __construct(
        private readonly BusinessSettingRepository $settingRepository,
        private readonly BusinessKnowledgeCompiler $knowledgeCompiler,
        private readonly EventBusInterface $eventBus,
        private readonly AuditService $auditService
    ) {}

    public function execute(UpdateBusinessSettingsInput $input): UpdateBusinessSettingsResult
    {
        $settings = $this->settingRepository->updateOrCreate($input->tenantId, [
            'business_name' => $input->businessName,
            'business_type' => $input->businessType,
            'phone' => $input->phone,
            'whatsapp_phone' => $input->whatsappPhone,
            'address' => $input->address,
            'timezone' => $input->timezone,
            'currency' => $input->currency,
            'delivery_enabled' => $input->deliveryEnabled,
            'delivery_fee' => $input->deliveryFee,
            'min_order_amount' => $input->minOrderAmount,
            'terms_and_conditions' => $input->termsAndConditions,
            'preferences' => $input->preferences,
        ]);

        // Recompilar snapshot de la base de conocimiento en Redis para la IA
        $this->knowledgeCompiler->compile($input->tenantId);

        // Auditoría & Eventos
        $this->auditService->record('Business', 'BusinessSettingsUpdated', newValues: ['name' => $settings->business_name]);
        $this->eventBus->dispatch(new BusinessSettingsUpdated($settings));

        return new UpdateBusinessSettingsResult($settings);
    }
}
