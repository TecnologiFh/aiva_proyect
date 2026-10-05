<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Raw Menu Evidences (RF-02, RF-04)
        if (!Schema::hasTable('raw_menu_evidences')) {
            Schema::create('raw_menu_evidences', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('tenant_id');
                $table->string('document_id')->nullable();
                $table->string('source_type')->default('WHATSAPP_IMAGE');
                $table->string('source_reference')->nullable();
                $table->text('extracted_text');
                $table->enum('element_type', ['text', 'heading', 'price', 'group', 'unknown'])->default('text');
                $table->json('bbox_json')->nullable(); // {x, y, width, height}
                $table->integer('page')->default(1);
                $table->string('region')->nullable();
                $table->float('confidence')->default(1.0);
                $table->integer('source_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'document_id']);
            });
        }

        // 2. Menu Groups (RF-12, RF-27)
        if (!Schema::hasTable('menu_groups')) {
            Schema::create('menu_groups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('normalized_name');
                $table->decimal('price', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('PEN');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('source_evidence_id')->nullable();
                $table->float('confidence')->default(1.0);
                $table->enum('status', ['DRAFT', 'NORMALIZED', 'REVIEW_REQUIRED', 'PUBLISHED', 'SUPERSEDED', 'REJECTED'])->default('DRAFT');
                $table->timestamps();

                $table->unique(['tenant_id', 'normalized_name'], 'menu_groups_tenant_norm_unique');
            });
        }

        // 3. Product Menu Associations (RF-02, RF-03, RF-28)
        if (!Schema::hasTable('product_menu_associations')) {
            Schema::create('product_menu_associations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('menu_group_id')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable();
                $table->enum('offering_type', ['INCLUDED', 'SURCHARGE', 'STANDALONE'])->default('INCLUDED');
                $table->decimal('surcharge_amount', 10, 2)->default(0.00);
                
                // 5 Factores de Scoring (RF-03)
                $table->float('spatial_score')->default(0.0);
                $table->float('semantic_score')->default(0.0);
                $table->float('heading_score')->default(0.0);
                $table->float('price_score')->default(0.0);
                $table->float('layout_score')->default(0.0);
                $table->float('global_confidence')->default(0.0);

                $table->enum('status', ['CONFIRMED', 'REVIEW_REQUIRED', 'AMBIGUOUS', 'REJECTED'])->default('CONFIRMED');
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'product_id', 'menu_group_id']);
            });
        }

        // 4. Association Multi-Evidences Pivot (RF-29)
        if (!Schema::hasTable('product_menu_association_evidences')) {
            Schema::create('product_menu_association_evidences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('association_id');
                $table->unsignedBigInteger('evidence_id');
                $table->string('signal_type')->default('spatial');
                $table->timestamps();

                $table->index(['association_id', 'evidence_id']);
            });
        }

        // 5. Canonical Catalogs (RF-07, RF-31)
        if (!Schema::hasTable('canonical_catalogs')) {
            Schema::create('canonical_catalogs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('catalog_version_id')->nullable();
                $table->integer('version_number')->default(1);
                $table->enum('status', ['DRAFT', 'NORMALIZED', 'REVIEW_REQUIRED', 'PUBLISHED', 'SUPERSEDED', 'REJECTED'])->default('DRAFT');
                $table->string('source_document_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('superseded_at')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'version_number'], 'canonical_tenant_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_catalogs');
        Schema::dropIfExists('product_menu_association_evidences');
        Schema::dropIfExists('product_menu_associations');
        Schema::dropIfExists('menu_groups');
        Schema::dropIfExists('raw_menu_evidences');
    }
};
