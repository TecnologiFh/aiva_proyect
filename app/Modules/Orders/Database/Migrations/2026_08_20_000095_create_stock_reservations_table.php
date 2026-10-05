<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('status', 30)->default('RESERVED'); // PENDING, RESERVED, CONFIRMED, EXPIRED, CANCELLED, RELEASED
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'product_id', 'status'], 'idx_tenant_product_res_status');
                $table->index(['tenant_id', 'expires_at', 'status'], 'idx_tenant_exp_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
