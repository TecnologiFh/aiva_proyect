<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. media_assets
        if (!Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('commercial_media_id')->nullable()->index();
                $table->uuid('lineage_id')->index();
                $table->string('storage_key', 500);
                $table->string('file_path', 500);
                $table->string('mime_type', 100)->default('image/png');
                $table->string('media_type', 50)->default('MENU_CARD'); // MENU_CARD, PRODUCT_CATALOG, PROMO_FLYER, LOOKBOOK
                $table->string('category', 100)->nullable();
                $table->string('title', 255);
                $table->string('sha256', 64)->index();
                $table->unsignedBigInteger('catalog_version_id')->nullable()->index();
                $table->unsignedInteger('display_order')->default(1);
                $table->unsignedInteger('revision_number')->default(1);
                $table->string('status', 50)->default('ACTIVE'); // ACTIVE, SUPERSEDED, ARCHIVED
                $table->boolean('is_dispatchable')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'id'], 'idx_ma_tenant_id');
                $table->index(['tenant_id', 'category', 'status'], 'idx_ma_tenant_cat_status');
            });
        }

        // 2. media_visual_regions
        if (!Schema::hasTable('media_visual_regions')) {
            Schema::create('media_visual_regions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('media_asset_id')->index();
                $table->unsignedBigInteger('parent_region_id')->nullable()->index();
                $table->string('region_type', 50)->default('CATEGORY_SECTION'); // HEADER, CATEGORY_SECTION, PRODUCT_BLOCK, PROMO_BANNER, FOOTER, SIDEBAR
                $table->string('label', 150);
                $table->string('normalized_label', 150)->index();
                $table->decimal('bbox_x', 6, 4)->default(0.0);
                $table->decimal('bbox_y', 6, 4)->default(0.0);
                $table->decimal('bbox_w', 6, 4)->default(1.0);
                $table->decimal('bbox_h', 6, 4)->default(1.0);
                $table->unsignedInteger('reading_order')->default(1);
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->timestamps();

                $table->index(['tenant_id', 'media_asset_id'], 'idx_mvr_tenant_asset');
            });
        }

        // 3. media_visual_observations
        if (!Schema::hasTable('media_visual_observations')) {
            Schema::create('media_visual_observations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('media_asset_id')->index();
                $table->unsignedBigInteger('region_id')->nullable()->index();
                $table->text('raw_text');
                $table->text('normalized_text')->nullable();
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->decimal('bbox_x', 6, 4)->default(0.0);
                $table->decimal('bbox_y', 6, 4)->default(0.0);
                $table->decimal('bbox_w', 6, 4)->default(1.0);
                $table->decimal('bbox_h', 6, 4)->default(1.0);
                $table->json('detected_features_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'media_asset_id'], 'idx_mvo_tenant_asset');
            });
        }

        // 4. media_visual_entities
        if (!Schema::hasTable('media_visual_entities')) {
            Schema::create('media_visual_entities', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('media_asset_id')->index();
                $table->unsignedBigInteger('region_id')->index();
                $table->unsignedBigInteger('observation_id')->nullable()->index();
                $table->string('entity_type', 50); // CATEGORY, PRODUCT, DESCRIPTION, PRICE, PROMOTION, IMAGE, DECORATION, BRAND_LOGO, BACKGROUND
                $table->string('visual_role', 50)->default('PRODUCT_REPRESENTATION'); // PRODUCT_REPRESENTATION, SECTION_REPRESENTATION, DECORATION, BRAND, UNKNOWN
                $table->string('raw_text', 255);
                $table->string('normalized_label', 255)->index();
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->decimal('bbox_x', 6, 4)->default(0.0);
                $table->decimal('bbox_y', 6, 4)->default(0.0);
                $table->decimal('bbox_w', 6, 4)->default(1.0);
                $table->decimal('bbox_h', 6, 4)->default(1.0);
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'media_asset_id', 'region_id'], 'idx_mve_tenant_asset_region');
            });
        }

        // 5. media_visual_relations
        if (!Schema::hasTable('media_visual_relations')) {
            Schema::create('media_visual_relations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('media_asset_id')->index();
                $table->unsignedBigInteger('source_entity_id')->index();
                $table->string('relation_type', 50); // BELONGS_TO, DESCRIBES, PRICED_BY, GROUPED_WITH, LOCATED_NEAR, UNDER_SECTION, VISUAL_ASSOCIATION, SAME_ROW, SAME_COLUMN, PART_OF
                $table->unsignedBigInteger('target_entity_id')->index();
                $table->decimal('confidence', 5, 4)->default(1.0);
                $table->timestamps();

                $table->index(['tenant_id', 'media_asset_id'], 'idx_mvrel_tenant_asset');
                $table->index(['source_entity_id', 'relation_type', 'target_entity_id'], 'idx_mvrel_triplet');
            });
        }

        // 6. media_semantic_concepts
        if (!Schema::hasTable('media_semantic_concepts')) {
            Schema::create('media_semantic_concepts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('concept', 150);
                $table->string('normalized_concept', 150)->index();
                $table->string('concept_type', 50)->default('CATEGORY'); // CATEGORY, PREPARATION, INGREDIENT, PORTION_SIZE, PROMOTION, PRESENTATION
                $table->decimal('relevance_weight', 4, 2)->default(1.00);
                $table->timestamps();

                $table->unique(['tenant_id', 'normalized_concept'], 'uq_msc_tenant_concept');
            });
        }

        // 7. media_semantic_concept_relations
        if (!Schema::hasTable('media_semantic_concept_relations')) {
            Schema::create('media_semantic_concept_relations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('source_concept_id')->index();
                $table->string('relation_type', 50)->default('RELATED_TO'); // RELATED_TO, SUB_CONCEPT_OF, SYNONYM_OF, DERIVED_FROM
                $table->unsignedBigInteger('target_concept_id')->index();
                $table->decimal('strength', 4, 2)->default(1.00);
                $table->timestamps();

                $table->unique(['tenant_id', 'source_concept_id', 'relation_type', 'target_concept_id'], 'uq_mscr_edge');
            });
        }

        // 8. media_semantic_index
        if (!Schema::hasTable('media_semantic_index')) {
            Schema::create('media_semantic_index', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('concept_id')->index();
                $table->unsignedBigInteger('media_asset_id')->index();
                $table->unsignedBigInteger('region_id')->nullable()->index();
                $table->unsignedBigInteger('entity_id')->nullable()->index();
                $table->string('evidence_type', 50)->default('DIRECT_CONCEPT'); // DIRECT_CONCEPT, CATEGORY_MATCH, PRODUCT_MATCH, REGION_MATCH, RELATION_MATCH, VISUAL_ASSOCIATION, CONTEXT_MATCH
                $table->decimal('match_score', 5, 4)->default(1.0000);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'concept_id'], 'idx_msi_tenant_concept');
                $table->index(['tenant_id', 'media_asset_id'], 'idx_msi_tenant_asset');
            });
        }

        // 9. conversation_visual_contexts
        if (!Schema::hasTable('conversation_visual_contexts')) {
            Schema::create('conversation_visual_contexts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('conversation_id', 100)->index();
                $table->unsignedBigInteger('last_media_asset_id')->nullable()->index();
                $table->string('last_media_target', 150)->nullable();
                $table->string('last_media_category', 100)->nullable();
                $table->string('last_media_caption', 500)->nullable();
                $table->string('last_visual_referent', 255)->nullable();
                $table->timestamp('last_media_dispatched_at')->nullable();
                $table->json('context_meta_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'conversation_id'], 'uq_cvc_tenant_conv');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_visual_contexts');
        Schema::dropIfExists('media_semantic_index');
        Schema::dropIfExists('media_semantic_concept_relations');
        Schema::dropIfExists('media_semantic_concepts');
        Schema::dropIfExists('media_visual_relations');
        Schema::dropIfExists('media_visual_entities');
        Schema::dropIfExists('media_visual_observations');
        Schema::dropIfExists('media_visual_regions');
        Schema::dropIfExists('media_assets');
    }
};
