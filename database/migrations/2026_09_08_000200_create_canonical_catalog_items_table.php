<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('canonical_catalog_items')) {
            Schema::create('canonical_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('catalog_version_id')->nullable()->index();
                $table->unsignedBigInteger('schema_profile_id')->nullable()->index();
                $table->string('sku', 100)->nullable();
                $table->string('name', 255);
                $table->string('normalized_name', 255)->index();
                $table->string('category', 150)->index();
                $table->text('description')->nullable();
                $table->decimal('base_price', 10, 2)->default(0.00);
                $table->json('compatibility_json')->nullable();
                $table->json('attributes_json')->nullable();
                $table->integer('stock')->nullable(); // NULL = no informado en fuente (prohibido default 0)
                $table->string('stock_status', 50)->default('UNKNOWN'); // IN_STOCK, LOW_STOCK, OUT_OF_STOCK, UNKNOWN
                $table->json('provenance_json')->nullable(); // source_file, source_row, raw_values, etc.
                $table->string('status', 50)->default('ACTIVE'); // ACTIVE, INACTIVE, DRAFT, SUPERSEDED
                $table->timestamps();

                $table->index(['tenant_id', 'catalog_version_id'], 'idx_cci_tenant_catver');
                $table->index(['tenant_id', 'sku'], 'idx_cci_tenant_sku');
                $table->index(['tenant_id', 'status'], 'idx_cci_tenant_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_catalog_items');
    }
};
