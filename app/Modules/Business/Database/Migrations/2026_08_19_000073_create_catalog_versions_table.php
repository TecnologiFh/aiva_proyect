<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            $table->unsignedInteger('version_number');
            $table->string('changelog_summary', 255)->nullable();
            $table->unsignedInteger('published_items_count')->default(0);
            $table->unsignedBigInteger('published_by')->nullable();
            $table->json('version_snapshot')->nullable(); // Foto JSON del catálogo publicado
            
            $table->timestamps();
            
            $table->unique(['tenant_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_versions');
    }
};
