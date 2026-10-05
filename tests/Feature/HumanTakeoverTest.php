<?php

namespace Tests\Feature;

use App\Core\Enums\ConversationMode;
use App\Modules\Messaging\Models\Conversation;
use Tests\TestCase;

class HumanTakeoverTest extends TestCase
{
    public function test_conversation_mode_switch_to_human(): void
    {
        $conversation = new Conversation([
            'tenant_id' => 1,
            'customer_phone' => '+51987654321',
            'mode' => ConversationMode::AI->value,
        ]);

        $this->assertEquals(ConversationMode::AI, $conversation->mode);

        $conversation->mode = ConversationMode::HUMAN;
        $this->assertEquals(ConversationMode::HUMAN, $conversation->mode);
    }
}
