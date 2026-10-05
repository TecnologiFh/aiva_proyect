<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inbound_webhook_events')) {
            Schema::create('inbound_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('tenant_id');
                $table->string('whatsapp_message_id');
                $table->string('media_id')->nullable();
                $table->enum('status', ['RECEIVED', 'PROCESSING', 'PROCESSED', 'FAILED'])->default('RECEIVED');
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'whatsapp_message_id'], 'inbound_webhook_tenant_msg_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhook_events');
    }
};
