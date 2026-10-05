<?php

namespace App\Core\Events;

use App\Modules\Messaging\Models\Message;

class MessageSent
{
    public function __construct(
        public readonly Message $message
    ) {}
}
