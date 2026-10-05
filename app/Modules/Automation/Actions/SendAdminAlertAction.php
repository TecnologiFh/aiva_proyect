<?php

namespace App\Modules\Automation\Actions;

use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;
use App\Modules\Tenant\Models\TenantSetting;

class SendAdminAlertAction implements AutomationActionInterface
{
    public function __construct(
        private readonly SendMessageAction $sendMessageAction
    ) {}

    public function execute(array $params, array $context): bool
    {
        $tenantId = $context['tenant_id'] ?? null;
        $alertMessage = $params['resolved_message'] ?? ($params['message'] ?? 'Alerta administrativa de AIVA');

        if (!$tenantId) {
            return false;
        }

        $setting = TenantSetting::where('tenant_id', $tenantId)->first();
        $adminPhone = $setting?->preferences['admin_whatsapp_phone'] ?? null;

        if ($adminPhone) {
            $this->sendMessageAction->execute(new SendMessageInput(
                tenantId: $tenantId,
                recipientPhone: $adminPhone,
                content: "🚨 *ALERTA ADMINISTRATIVA:* {$alertMessage}",
                senderType: 'SYSTEM'
            ));
        }

        return true;
    }

    public function getType(): string
    {
        return 'SEND_ADMIN_ALERT';
    }
}
