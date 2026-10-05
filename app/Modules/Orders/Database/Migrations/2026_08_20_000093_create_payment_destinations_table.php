<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_destinations')) {
            Schema::create('payment_destinations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('provider', 30); // YAPE, PLIN, BCP, INTERBANK, BBVA
                $table->string('holder_name', 150);
                $table->string('phone', 50)->nullable();
                $table->string('account_number', 100)->nullable();
                $table->string('cci', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'provider', 'is_active'], 'idx_tenant_prov_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_destinations');
    }
};
