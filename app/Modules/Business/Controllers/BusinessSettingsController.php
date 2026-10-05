<?php

namespace App\Modules\Business\Controllers;

use App\Core\Services\TenantContext;
use App\Modules\Business\Application\BusinessSettings\UpdateBusinessSettings\UpdateBusinessSettingsAction;
use App\Modules\Business\Application\BusinessSettings\UpdateBusinessSettings\UpdateBusinessSettingsInput;
use App\Modules\Business\Repositories\BusinessSettingRepository;
use App\Modules\Business\Requests\UpdateBusinessSettingsRequest;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BusinessSettingsController extends Controller
{
    public function __construct(
        private readonly BusinessSettingRepository $settingRepository
    ) {}

    public function show(): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();
        $settings = $this->settingRepository->getByTenantId($tenantId);

        return ApiResponse::success(
            data: $settings ? [
                'business_name' => $settings->business_name,
                'business_type' => $settings->business_type->value,
                'phone' => $settings->phone,
                'whatsapp_phone' => $settings->whatsapp_phone,
                'address' => $settings->address,
                'timezone' => $settings->timezone,
                'currency' => $settings->currency->value,
                'delivery_enabled' => $settings->delivery_enabled,
                'delivery_fee' => (float) $settings->delivery_fee,
                'min_order_amount' => (float) $settings->min_order_amount,
                'terms_and_conditions' => $settings->terms_and_conditions,
                'preferences' => $settings->preferences,
            ] : null,
            message: 'Configuración comercial recuperada exitosamente'
        );
    }

    public function update(UpdateBusinessSettingsRequest $request, UpdateBusinessSettingsAction $action): JsonResponse
    {
        $tenantId = TenantContext::getTenantId();

        $input = new UpdateBusinessSettingsInput(
            tenantId: $tenantId,
            businessName: $request->validated('business_name'),
            businessType: $request->validated('business_type', 'RESTAURANT'),
            phone: $request->validated('phone'),
            whatsappPhone: $request->validated('whatsapp_phone'),
            address: $request->validated('address'),
            timezone: $request->validated('timezone', 'America/Lima'),
            currency: $request->validated('currency', 'PEN'),
            deliveryEnabled: (bool) $request->validated('delivery_enabled', true),
            deliveryFee: (float) $request->validated('delivery_fee', 0.00),
            minOrderAmount: (float) $request->validated('min_order_amount', 0.00),
            termsAndConditions: $request->validated('terms_and_conditions'),
            preferences: $request->validated('preferences')
        );

        $result = $action->execute($input);

        return ApiResponse::success(
            data: $result->toArray(),
            message: 'Configuración comercial actualizada correctamente'
        );
    }
}
