<?php

namespace Tests\Feature;

use App\Core\Enums\ConversationMode;
use App\Core\Enums\ConversationStatus;
use App\Modules\Messaging\Models\Conversation;

use Tests\TestCase;

class WhatsAppConversationFlowTest extends TestCase
{
    public function test_conversation_model_casts_and_attributes(): void
    {
        $conversation = new Conversation([
            'tenant_id' => 1,
            'customer_phone' => '+51999888777',
            'customer_name' => 'Luis Fiestas',
            'status' => ConversationStatus::OPEN->value,
            'mode' => ConversationMode::AI->value,
        ]);

        $this->assertEquals(1, $conversation->tenant_id);
        $this->assertEquals('+51999888777', $conversation->customer_phone);
        $this->assertEquals(ConversationStatus::OPEN, $conversation->status);
        $this->assertEquals(ConversationMode::AI, $conversation->mode);
    }
}
