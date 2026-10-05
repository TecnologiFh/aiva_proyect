<?php

namespace App\Core\Events;

use App\Modules\Messaging\Models\Message;

class MessageReceived
{
    public function __construct(
        public readonly Message $message
    ) {}
}
