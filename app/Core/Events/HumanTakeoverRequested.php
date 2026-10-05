<?php

namespace App\Core\Events;

use App\Modules\Messaging\Models\Conversation;

class HumanTakeoverRequested
{
    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $reason
    ) {}
}
