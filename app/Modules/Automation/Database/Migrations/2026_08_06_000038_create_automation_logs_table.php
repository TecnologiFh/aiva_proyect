<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->unsignedInteger('rule_version')->default(1);
            $table->string('trigger_name', 100);
            $table->string('status', 30)->default('SUCCESS'); // SUCCESS, FAILED, SKIPPED
            $table->json('execution_details')->nullable();
            $table->timestamp('executed_at')->useCurrent();

            $table->index(['rule_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
