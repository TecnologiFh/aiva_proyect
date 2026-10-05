<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150)->default('Cliente');
            $table->string('email', 150)->nullable();
            $table->string('customer_stage', 30)->default('NEW'); // NEW, ACTIVE, LOYAL, VIP, INACTIVE, LOST
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_stage']);
        });

        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('channel', 30); // WHATSAPP, INSTAGRAM, TELEGRAM, WEB
            $table->string('identifier', 150); // +51999888777, @luis
            $table->timestamps();

            $table->unique(['tenant_id', 'channel', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
        Schema::dropIfExists('customers');
    }
};
