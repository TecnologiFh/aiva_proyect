<?php

namespace App\Modules\Messaging\Providers;

use App\Core\Contracts\Messaging\WhatsAppClientInterface;
use App\Modules\Messaging\DTOs\OutgoingWhatsAppMessageDTO;
use Illuminate\Support\Facades\Log;

class MockWhatsAppClient implements WhatsAppClientInterface
{
    private static array $sentMessages = [];

    public function sendMessage(OutgoingWhatsAppMessageDTO $message): bool
    {
        self::$sentMessages[] = $message;

        Log::info("MockWhatsAppClient: Mensaje simulado enviado a {$message->recipientPhone}: {$message->content}");

        return true;
    }

    public function markAsRead(string $wamid): bool
    {
        Log::info("MockWhatsAppClient: Mensaje {$wamid} marcado como leído.");
        return true;
    }

    public static function getSentMessages(): array
    {
        return self::$sentMessages;
    }

    public static function clearSentMessages(): void
    {
        self::$sentMessages = [];
    }
}
