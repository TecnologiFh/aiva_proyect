<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('customer_phone', 50);
            $table->string('customer_name', 150)->nullable();
            $table->string('status', 30)->default('OPEN'); // OPEN, CLOSED, ARCHIVED
            $table->string('mode', 30)->default('AI');      // AI, HUMAN, HYBRID
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_phone']);
            $table->index(['tenant_id', 'status', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
