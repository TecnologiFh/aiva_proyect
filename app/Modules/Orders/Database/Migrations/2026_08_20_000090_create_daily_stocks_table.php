<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_stocks')) {
            Schema::create('daily_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('initial_stock')->default(0);
                $table->unsignedInteger('available_stock')->default(0);
                $table->unsignedInteger('reserved_stock')->default(0);
                $table->unsignedInteger('sold_stock')->default(0);
                $table->string('status', 30)->default('ACTIVE'); // ACTIVE, SOLD_OUT
                $table->timestamps();

                $table->unique(['tenant_id', 'product_id', 'date'], 'uk_tenant_product_date');
                $table->index(['tenant_id', 'date', 'status'], 'idx_tenant_date_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stocks');
    }
};
