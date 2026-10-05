<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('business_sources')->nullOnDelete();
            
            $table->string('item_type', 50)->default('PRODUCT_CANDIDATE'); // PRODUCT_CANDIDATE, POLICY_CANDIDATE, INFO_FACT
            $table->string('detected_name', 200);
            $table->string('detected_category', 100)->nullable();
            $table->decimal('detected_price', 10, 2)->nullable();
            $table->text('detected_description')->nullable();
            $table->json('extracted_metadata')->nullable(); // Atributos, SKU detectado, confianza
            
            $table->decimal('confidence_score', 4, 3)->default(0.950); // ej: 0.970
            $table->string('status', 30)->default('PENDING_APPROVAL'); // PENDING_APPROVAL, APPROVED, REJECTED, EDITED
            
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
