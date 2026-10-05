<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('trigger_type', 30)->default('EVENT'); // EVENT, SCHEDULE
            $table->string('trigger_name', 100); // ORDER_CREATED, PAYMENT_APPROVED, LOW_STOCK_DETECTED...
            $table->string('cron_expression', 50)->nullable();
            $table->json('logic_tree'); // {operator: 'AND', rules: [...]}
            $table->json('actions'); // [{action_type: 'SEND_WHATSAPP', delay_seconds: 0, params: {...}}]
            $table->unsignedInteger('priority')->default(50);
            $table->boolean('stop_processing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('executions_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'trigger_name', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
