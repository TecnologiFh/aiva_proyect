<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                
                $table->string('zone_name', 100); // ej: Miraflores, Surco, San Miguel, Los Olivos
                $table->decimal('delivery_fee', 10, 2)->default(0.00);
                $table->unsignedInteger('estimated_time_mins')->default(45);
                $table->decimal('min_order_amount', 10, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                
                $table->timestamps();
                
                $table->unique(['tenant_id', 'zone_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
