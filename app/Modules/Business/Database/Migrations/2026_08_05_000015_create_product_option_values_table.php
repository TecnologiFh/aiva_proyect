<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('product_options')->cascadeOnDelete();
            $table->string('name', 100); // Personal, Familiar, BBQ
            $table->decimal('price_modifier', 10, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
