<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            $table->string('source_type', 50); // DOCUMENT_PDF, EXCEL_CSV, IMAGE_MENU, WEB_URL, MANUAL_NOTE
            $table->string('title', 150);
            $table->string('file_path', 500)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->text('raw_content')->nullable(); // Texto crudo extraído o nota manual
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            
            $table->string('status', 30)->default('PENDING_EXTRACTION'); // PENDING_EXTRACTION, EXTRACTED, ERROR
            $table->text('error_log')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sources');
    }
};
