<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('handover_attentions')) {
            Schema::create('handover_attentions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('attention_code', 50);
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->string('customer_phone', 50)->nullable();
                $table->string('customer_name', 150)->nullable();
                $table->string('reason', 150)->default('AI_UNCERTAINTY'); // AI_UNCERTAINTY, EXPLICIT_HUMAN_REQUEST, COMPLAINT, PAYMENT_DISCREPANCY
                $table->string('status', 30)->default('CREATED'); // CREATED, ACKNOWLEDGED, RESOLVED
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->unique(['tenant_id', 'attention_code'], 'uk_tenant_attention_code');
                $table->index(['tenant_id', 'status'], 'idx_tenant_att_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_attentions');
    }
};
