<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->text('description')->nullable();
            $table->text('search_keywords')->nullable();
            $table->string('product_type', 30)->default('PRODUCT'); // PRODUCT, SERVICE
            $table->string('sku', 100)->nullable();
            $table->decimal('base_price', 10, 2)->default(0.00);
            $table->string('image_path', 255)->nullable();
            $table->string('status', 30)->default('ACTIVE'); // ACTIVE, INACTIVE, OUT_OF_STOCK
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug', 'deleted_at']);
            $table->unique(['tenant_id', 'sku', 'deleted_at']);
            $table->index(['tenant_id', 'category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
