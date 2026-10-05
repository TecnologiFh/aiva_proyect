<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->default(1); // Preparado para múltiples sucursales
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('current_stock', 10, 2)->default(0.00);
            $table->decimal('reserved_stock', 10, 2)->default(0.00);
            $table->decimal('minimum_stock', 10, 2)->default(0.00);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_out_of_stock')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'product_id']);
            $table->index(['tenant_id', 'branch_id', 'is_out_of_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
