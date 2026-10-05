<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('business_name', 150);
            $table->string('business_type', 50)->default('RESTAURANT'); // Enum BusinessType
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('timezone', 50)->default('America/Lima');
            $table->string('currency', 10)->default('PEN'); // Enum Currency
            $table->boolean('delivery_enabled')->default(true);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('min_order_amount', 10, 2)->default(0.00);
            $table->text('terms_and_conditions')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
