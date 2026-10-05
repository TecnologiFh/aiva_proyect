<?php

namespace App\Core\Contracts\Messaging;

use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;

interface WhatsAppClientInterface
{
    /**
     * Envía un mensaje saliente a través de WhatsApp.
     */
    public function sendMessage(OutgoingWhatsAppMessageDTO $message): bool;

    /**
     * Envía la confirmación de lectura de un mensaje entrante (wamid).
     */
    public function markAsRead(string $wamid): bool;
}
