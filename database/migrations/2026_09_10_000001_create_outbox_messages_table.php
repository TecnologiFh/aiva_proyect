<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedInteger('sequence_number');
            $table->string('recipient_phone');
            $table->string('message_type', 32)->default('TEXT'); // TEXT, IMAGE, INTERACTIVE, etc.
            $table->json('payload_json'); // Invariant Immutable Snapshot
            $table->enum('status', [
                'PENDING',
                'DISPATCHING',
                'SENT',
                'RETRYING',
                'DEAD_LETTER',
                'DISPATCH_AMBIGUOUS_RECONCILIATION_REQUIRED',
                'FORENSIC_UNRESOLVED'
            ])->default('PENDING');
            $table->unsignedInteger('attempts')->default(0); // Count of actual executed HTTP POSTs
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('whatsapp_message_id')->nullable()->index();
            $table->string('correlation_token')->nullable()->index(); // Unequivocal Correlation Token
            $table->string('last_http_status', 16)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Constitutional DB Constraints
            $table->unique(['tenant_id', 'conversation_id', 'sequence_number'], 'uniq_tenant_conv_seq');
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'next_retry_at', 'lease_expires_at'], 'idx_outbox_claim_eligibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
