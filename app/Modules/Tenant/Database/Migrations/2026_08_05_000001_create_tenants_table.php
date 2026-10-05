<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // BINARY(16) vía HasBinaryUuid trait
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('business_type', 100);
            $table->string('country', 50)->default('PE');
            $table->string('currency', 10)->default('PEN');
            $table->string('timezone', 50)->default('America/Lima');
            $table->string('status', 30)->default('ACTIVE');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
