<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_events')) {
            Schema::create('order_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                
                $table->string('event_type', 50); // ORDER_CREATED, PAYMENT_RECEIVED, PREPARATION_STARTED, READY_FOR_DISPATCH, OUT_FOR_DELIVERY, DELIVERED
                $table->string('previous_state', 50)->nullable();
                $table->string('new_state', 50);
                
                $table->string('actor_type', 30)->default('SYSTEM'); // AI, HUMAN, SYSTEM, CUSTOMER, PAYMENT_PROVIDER, DELIVERY
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                
                $table->timestamp('created_at')->useCurrent();
                
                $table->index(['tenant_id', 'order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
