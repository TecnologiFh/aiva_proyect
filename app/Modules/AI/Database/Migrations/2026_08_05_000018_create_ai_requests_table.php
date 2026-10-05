<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('provider', 50); // OPENAI, GEMINI, MOCK
            $table->string('model', 50);    // gpt-4o-mini, gemini-1.5-flash
            $table->string('prompt_version', 20)->default('v1.0');
            $table->string('intent_detected', 50)->nullable();
            $table->decimal('confidence', 4, 3)->default(1.000);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 6)->default(0.000000);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 30)->default('SUCCESS');
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
