<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. commercial_media
        if (!Schema::hasTable('commercial_media')) {
            Schema::create('commercial_media', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->uuid('lineage_id')->index();
                $table->string('media_type', 50)->default('MENU_CARD'); // MENU_CARD, PRODUCT_CATALOG, LOOKBOOK
                $table->string('category', 100)->nullable();
                $table->string('title', 255);
                $table->string('file_path', 500);
                $table->string('content_hash', 64)->index(); // SHA-256
                $table->unsignedInteger('revision_number')->default(1);
                $table->unsignedBigInteger('previous_media_id')->nullable()->index();
                $table->string('mime_type', 100)->default('image/png');
                $table->unsignedBigInteger('file_size_bytes')->default(0);
                $table->unsignedInteger('display_order')->default(1);
                $table->string('status', 50)->default('ACTIVE'); // ACTIVE, SUPERSEDED, ARCHIVED
                $table->timestamps();

                $table->index(['tenant_id', 'category', 'status'], 'idx_cm_tenant_cat_status');
                $table->unique(['tenant_id', 'lineage_id', 'revision_number'], 'uq_cm_revision');
            });
        }

        // 2. commercial_media_aliases
        if (!Schema::hasTable('commercial_media_aliases')) {
            Schema::create('commercial_media_aliases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('commercial_media_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('alias', 150);
                $table->decimal('weight', 4, 2)->default(1.00);
                $table->timestamps();

                $table->index(['tenant_id', 'alias'], 'idx_cma_tenant_alias');
            });
        }

        // 3. catalog_version_media
        if (!Schema::hasTable('catalog_version_media')) {
            Schema::create('catalog_version_media', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_version_id')->index();
                $table->unsignedBigInteger('commercial_media_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->timestamp('created_at')->nullable();

                $table->index(['tenant_id', 'catalog_version_id'], 'idx_cvm_tenant_version');
            });
        }

        // 4. product_variants
        if (!Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('sku', 100)->nullable()->index();
                $table->decimal('price_override', 10, 2)->nullable();
                $table->string('color', 50)->nullable();
                $table->string('talla', 50)->nullable();
                $table->integer('stock')->default(0);
                $table->string('status', 50)->default('PUBLISHED');
                $table->timestamps();

                $table->index(['tenant_id', 'product_id', 'status'], 'idx_pv_tenant_product');
            });
        }

        // 5. product_variant_attributes
        if (!Schema::hasTable('product_variant_attributes')) {
            Schema::create('product_variant_attributes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('variant_id')->index();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('attribute_name', 100);
                $table->string('attribute_value', 255);
                $table->timestamps();

                $table->index(['tenant_id', 'attribute_name', 'attribute_value'], 'idx_pva_tenant_attr');
            });
        }

        // 6. product_visual_references
        if (!Schema::hasTable('product_visual_references')) {
            Schema::create('product_visual_references', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('variant_id')->nullable()->index();
                $table->unsignedBigInteger('commercial_media_id')->index();
                $table->unsignedBigInteger('evidence_id')->index();
                $table->decimal('bbox_x', 6, 4)->default(0.0000);
                $table->decimal('bbox_y', 6, 4)->default(0.0000);
                $table->decimal('bbox_w', 6, 4)->default(0.0000);
                $table->decimal('bbox_h', 6, 4)->default(0.0000);
                $table->string('visual_label', 255)->nullable();
                $table->unsignedInteger('display_index')->default(1);
                $table->decimal('confidence', 4, 3)->default(1.000);
                $table->string('status', 50)->default('PUBLISHED'); // DRAFT, PUBLISHED, ARCHIVED
                $table->timestamps();

                $table->unique(['tenant_id', 'commercial_media_id', 'display_index'], 'uq_pvr_scoped');
            });

            // XOR Check Constraint (Anti-Orphan)
            try {
                DB::statement("ALTER TABLE product_visual_references ADD CONSTRAINT chk_pvr_xor CHECK ((product_id IS NOT NULL AND variant_id IS NULL) OR (product_id IS NULL AND variant_id IS NOT NULL))");
            } catch (\Throwable $e) {
                // If DB engine doesn't support ALTER CHECK, continue
            }
        }

        // 7. conversation_commercial_contexts
        if (!Schema::hasTable('conversation_commercial_contexts')) {
            Schema::create('conversation_commercial_contexts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('conversation_id', 150)->index();
                $table->unsignedBigInteger('catalog_version_id')->index();
                $table->unsignedBigInteger('last_commercial_media_id')->nullable()->index();
                $table->string('last_category', 100)->nullable();
                $table->unsignedInteger('last_display_order')->default(1);
                $table->timestamps();

                $table->unique(['tenant_id', 'conversation_id'], 'uq_ccc_tenant_conv');
            });
        }

        // 8. Add status and is_active to catalog_versions for C13 Single Active Published Catalog
        if (Schema::hasTable('catalog_versions')) {
            Schema::table('catalog_versions', function (Blueprint $table) {
                if (!Schema::hasColumn('catalog_versions', 'status')) {
                    $table->string('status', 50)->default('PUBLISHED')->index();
                }
                if (!Schema::hasColumn('catalog_versions', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_commercial_contexts');
        Schema::dropIfExists('product_visual_references');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('catalog_version_media');
        Schema::dropIfExists('commercial_media_aliases');
        Schema::dropIfExists('commercial_media');
    }
};
