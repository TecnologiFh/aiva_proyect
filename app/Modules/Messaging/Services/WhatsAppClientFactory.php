<?php

namespace App\Modules\Messaging\Services;

use App\Core\Contracts\Messaging\WhatsAppClientInterface;
use App\Modules\Messaging\Providers\MetaWhatsAppClient;
use App\Modules\Messaging\Providers\MockWhatsAppClient;

class WhatsAppClientFactory
{
    public function make(?string $driver = null): WhatsAppClientInterface
    {
        $selectedDriver = strtoupper($driver ?? config('services.whatsapp.driver', env('WHATSAPP_DRIVER', 'MOCK')));

        return match ($selectedDriver) {
            'META' => new MetaWhatsAppClient(),
            default => new MockWhatsAppClient(),
        };
    }
}
