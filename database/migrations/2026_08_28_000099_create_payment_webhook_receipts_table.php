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
        if (!Schema::hasTable('payment_webhook_receipts')) {
            Schema::create('payment_webhook_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('whatsapp_message_id', 255);
                $table->string('processing_status', 50)->default('COMPLETED');
                $table->string('result_code', 50)->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->unique(['tenant_id', 'whatsapp_message_id'], 'idx_pwr_tenant_wa_msg_unique');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_receipts');
    }
};
