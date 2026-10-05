<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained('inventory_stocks')->cascadeOnDelete();
            $table->string('movement_type', 30); // STOCK_IN, STOCK_OUT, ADJUSTMENT, RESERVATION
            $table->string('movement_reason', 30)->default('SALE'); // SALE, PURCHASE, WASTE, DAMAGE, RESERVATION...
            $table->decimal('quantity', 10, 2)->default(0.00);
            $table->decimal('previous_stock', 10, 2)->default(0.00);
            $table->decimal('new_stock', 10, 2)->default(0.00);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('total_cost', 10, 2)->default(0.00);
            $table->string('reference_type', 50)->nullable(); // ORDER, MANUAL, SYSTEM
            $table->string('reference_id', 100)->nullable();
            $table->string('performed_by_type', 30)->default('SYSTEM'); // USER, SYSTEM, AI
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['stock_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
