<?php

namespace App\Core\Events;

use App\Modules\Messaging\Models\Conversation;

class ConversationStarted
{
    public function __construct(
        public readonly Conversation $conversation
    ) {}
}
