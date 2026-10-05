<?php

namespace App\Modules\Messaging\Application\SendMessage;

use App\Modules\Messaging\Models\Message;

class SendMessageResult
{
    public function __construct(
        public readonly Message $message,
        public readonly bool $delivered
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->message->uuid,
            'recipient_phone' => $this->message->conversation?->customer_phone,
            'sender_type' => $this->message->sender_type->value,
            'content' => $this->message->content,
            'status' => $this->message->status->value,
            'delivered' => $this->delivered,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
