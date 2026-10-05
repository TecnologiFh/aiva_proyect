<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('catalog_version_validations')) {
            Schema::create('catalog_version_validations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('catalog_version_id')->index();
                $table->unsignedBigInteger('commercial_media_id')->nullable()->index();
                $table->unsignedInteger('expected_sellable_count')->default(0);
                $table->unsignedInteger('evidence_count')->default(0);
                $table->unsignedInteger('resolved_count')->default(0);
                $table->unsignedInteger('pvr_count')->default(0);
                $table->unsignedInteger('missing_count')->default(0);
                $table->json('missing_items_json')->nullable();
                $table->string('status', 50)->default('COMPLETE'); // COMPLETE, INCOMPLETE
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'catalog_version_id', 'status'], 'idx_cvv_tenant_ver_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_version_validations');
    }
};
