<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('gateway', 50); // MOCK, YAPE_PLIN, STRIPE, CULQI
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->string('merchant_id', 150)->nullable();
            $table->string('environment', 30)->default('SANDBOX'); // SANDBOX, PRODUCTION
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
