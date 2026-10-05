<?php

namespace App\Modules\Messaging\Repositories;

use App\Core\Enums\ConversationMode;
use App\Core\Enums\ConversationStatus;
use App\Modules\Messaging\Models\Conversation;

class ConversationRepository
{
    public function findOrCreate(int $tenantId, string $customerPhone, ?string $customerName = null): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_phone' => $customerPhone,
            ],
            [
                'customer_name' => $customerName,
                'status' => ConversationStatus::OPEN->value,
                'mode' => ConversationMode::AI->value,
                'last_message_at' => now(),
            ]
        );
    }

    public function findByUuid(string $uuid): ?Conversation
    {
        return Conversation::whereUuid($uuid)->first();
    }

    public function updateMode(int $id, ConversationMode $mode): Conversation
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->update(['mode' => $mode->value]);
        return $conversation;
    }

    public function updateStatus(int $id, ConversationStatus $status): Conversation
    {
        $conversation = Conversation::findOrFail($id);
        $conversation->update(['status' => $status->value]);
        return $conversation;
    }

    public function touchLastMessage(int $id): void
    {
        Conversation::where('id', $id)->update(['last_message_at' => now()]);
    }
}
