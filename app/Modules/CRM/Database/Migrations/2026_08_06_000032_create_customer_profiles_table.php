<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->unsignedInteger('total_orders_count')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0.00);
            $table->decimal('average_ticket', 10, 2)->default(0.00);
            $table->decimal('clv', 10, 2)->default(0.00);
            $table->decimal('cancellation_rate', 5, 2)->default(0.00);
            $table->unsignedInteger('customer_score')->default(50); // Score 0 a 100
            $table->unsignedInteger('orders_30d')->default(0);
            $table->unsignedInteger('orders_90d')->default(0);
            $table->string('favorite_category', 100)->nullable();
            $table->string('preferred_payment', 30)->nullable();
            $table->string('preferred_delivery', 30)->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'total_spent', 'customer_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
