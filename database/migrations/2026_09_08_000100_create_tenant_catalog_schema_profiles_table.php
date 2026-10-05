<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_catalog_schema_profiles')) {
            Schema::create('tenant_catalog_schema_profiles', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('profile_name', 150);
                $table->string('source_type', 50)->default('EXCEL');
                $table->string('schema_signature', 64)->index();
                $table->unsignedInteger('header_row_index')->default(1);
                $table->json('detected_columns');
                $table->json('column_mappings');
                $table->json('unmapped_columns')->nullable();
                $table->string('validation_status', 50)->default('VALID'); // VALID, REQUIRES_REVIEW, DRIFT_DETECTED, BREAKING_CHANGE
                $table->string('drift_category', 50)->default('NO_CHANGE'); // NO_CHANGE, COMPATIBLE_EXTENSION, MAPPING_REQUIRES_REVIEW, BREAKING_CHANGE
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'source_type', 'schema_signature', 'version'], 'idx_tcsp_tenant_sig_ver');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_catalog_schema_profiles');
    }
};
