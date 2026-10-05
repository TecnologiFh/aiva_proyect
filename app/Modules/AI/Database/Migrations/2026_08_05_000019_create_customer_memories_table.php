<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_memories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('customer_phone', 50);
            $table->string('memory_key', 100); // favorite_product, preferred_address
            $table->text('memory_value');
            $table->unsignedTinyInteger('importance_score')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_phone', 'memory_key']);
            $table->index(['tenant_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memories');
    }
};
