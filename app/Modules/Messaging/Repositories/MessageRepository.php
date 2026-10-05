<?php

namespace App\Modules\Messaging\Repositories;

use App\Modules\Messaging\Models\Message;
use Illuminate\Support\Collection;

class MessageRepository
{
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function getRecentMessages(int $conversationId, int $limit = 10): Collection
    {
        return Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
