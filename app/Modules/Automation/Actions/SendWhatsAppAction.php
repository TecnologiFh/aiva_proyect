<?php

namespace App\Modules\Automation\Actions;

use App\Modules\Messaging\Application\SendMessage\SendMessageAction;
use App\Modules\Messaging\Application\SendMessage\SendMessageInput;

class SendWhatsAppAction implements AutomationActionInterface
{
    public function __construct(
        private readonly SendMessageAction $sendMessageAction
    ) {}

    public function execute(array $params, array $context): bool
    {
        $tenantId = $context['tenant_id'] ?? null;
        $recipientPhone = $params['recipient_phone'] ?? ($context['customer']['phone'] ?? null);
        $content = $params['resolved_message'] ?? ($params['message'] ?? '');

        if (!$tenantId || !$recipientPhone || !$content) {
            return false;
        }

        $this->sendMessageAction->execute(new SendMessageInput(
            tenantId: $tenantId,
            recipientPhone: $recipientPhone,
            content: $content,
            senderType: 'AI'
        ));

        return true;
    }

    public function getType(): string
    {
        return 'SEND_WHATSAPP';
    }
}
