<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. visual_assets
        if (!Schema::hasTable('visual_assets')) {
            Schema::create('visual_assets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('source_file_path', 500);
                $table->string('file_hash_sha256', 64)->index();
                $table->string('mime_type', 100)->default('image/png');
                $table->json('dimensions_json')->nullable();
                $table->string('status', 50)->default('ACTIVE'); // ACTIVE, SUPERSEDED, ARCHIVED
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'file_hash_sha256'], 'idx_va_tenant_hash');
                $table->index(['tenant_id', 'status'], 'idx_va_tenant_status');
            });
        }

        // 2. visual_semantic_blocks
        if (!Schema::hasTable('visual_semantic_blocks')) {
            Schema::create('visual_semantic_blocks', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('asset_id')->index();
                $table->string('block_name', 150);
                $table->string('block_type', 50)->default('SECTION'); // SECTION, HEADER, GRID, FOOTER, PROMO_BANNER
                $table->json('region_box_json')->nullable(); // [ymin, xmin, ymax, xmax]
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->timestamps();

                $table->index(['tenant_id', 'asset_id'], 'idx_vsb_tenant_asset');
            });
        }

        // 3. visual_entities
        if (!Schema::hasTable('visual_entities')) {
            Schema::create('visual_entities', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('asset_id')->index();
                $table->unsignedBigInteger('block_id')->nullable()->index();
                $table->string('raw_label', 255);
                $table->string('normalized_label', 255)->index();
                $table->json('region_box_json')->nullable(); // [ymin, xmin, ymax, xmax]
                $table->decimal('observed_price', 10, 2)->nullable();
                $table->string('currency', 10)->default('PEN');
                $table->string('extraction_method', 50)->default('MULTIMODAL_SEMANTIC'); // DIRECT_OCR, MULTIMODAL_SEMANTIC, BOUNDING_REGION
                $table->decimal('evidence_confidence', 5, 4)->default(1.0);
                $table->string('provenance_classification', 50)->default('OBSERVED_FROM_IMAGE'); // OBSERVED_FROM_IMAGE, INFERRED_FROM_VISUAL_STRUCTURE, INFERRED_FROM_RELATION, MATCHED_TO_CATALOG, CONFIRMED_BY_USER
                $table->timestamps();

                $table->index(['tenant_id', 'asset_id', 'block_id'], 'idx_ve_tenant_asset_block');
            });
        }

        // 4. visual_relations
        if (!Schema::hasTable('visual_relations')) {
            Schema::create('visual_relations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('asset_id')->index();
                $table->unsignedBigInteger('source_entity_id')->index();
                $table->unsignedBigInteger('target_entity_id')->index();
                $table->string('relation_type', 50)->index(); // CONTAINS, BELONGS_TO, DESCRIBES, PRICED_AS, VARIANT_OF, MODIFIER_OF, COMBO_COMPONENT_OF, LOCATED_NEAR, SUPERSEDES
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->json('metadata_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'asset_id'], 'idx_vr_tenant_asset');
                $table->index(['source_entity_id', 'relation_type', 'target_entity_id'], 'idx_vr_triplet');
            });
        }

        // 5. visual_catalog_bindings
        if (!Schema::hasTable('visual_catalog_bindings')) {
            Schema::create('visual_catalog_bindings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('visual_entity_id')->index();
                $table->string('canonical_product_identifier', 100)->index(); // SKU contractual expuesto por AG-46
                $table->unsignedBigInteger('catalog_version_id')->index();
                $table->string('resolution_state', 50)->index(); // MATCH_CANONICAL, VISUAL_ONLY, AMBIGUOUS_CONFLICT, UNRESOLVED_INSUFFICIENT
                $table->string('temporal_status', 50)->default('VALID_CURRENT')->index(); // VALID_CURRENT, VALID_HISTORICAL, STALE, INVALIDATED
                $table->decimal('observed_price', 10, 2)->nullable();
                $table->decimal('authoritative_price', 10, 2)->nullable();
                $table->boolean('divergence_flag')->default(false)->index();
                $table->timestamp('last_reconciled_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'canonical_product_identifier', 'catalog_version_id'], 'idx_vcb_tenant_sku_ver');
            });
        }

        // 6. price_desynchronization_evidence
        if (!Schema::hasTable('price_desynchronization_evidence')) {
            Schema::create('price_desynchronization_evidence', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('visual_entity_id')->index();
                $table->string('canonical_product_identifier', 100)->index();
                $table->decimal('observed_price', 10, 2);
                $table->decimal('authoritative_price', 10, 2);
                $table->string('currency', 10)->default('PEN');
                $table->string('source_asset_hash', 64)->index();
                $table->unsignedBigInteger('catalog_version_id')->index();
                $table->timestamp('detected_at')->index();
                $table->string('resolution_policy', 50)->default('MYSQL_AUTHORITY_ENFORCED');
                $table->json('evidence_payload_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'canonical_product_identifier', 'detected_at'], 'idx_pde_tenant_sku_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_desynchronization_evidence');
        Schema::dropIfExists('visual_catalog_bindings');
        Schema::dropIfExists('visual_relations');
        Schema::dropIfExists('visual_entities');
        Schema::dropIfExists('visual_semantic_blocks');
        Schema::dropIfExists('visual_assets');
    }
};
