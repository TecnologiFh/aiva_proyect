<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enriquecer canonical_catalog_items para D5 (Precio ambiguo -> NULL y price_status)
        if (Schema::hasTable('canonical_catalog_items')) {
            try {
                DB::statement("ALTER TABLE `canonical_catalog_items` MODIFY COLUMN `base_price` DECIMAL(10,2) NULL DEFAULT NULL");
            } catch (\Throwable $e) {}

            Schema::table('canonical_catalog_items', function (Blueprint $table) {
                if (!Schema::hasColumn('canonical_catalog_items', 'price_status')) {
                    $table->string('price_status', 50)->default('CONFIRMED')->after('base_price');
                }
            });
        }

        // 2. Enriquecer catalog_versions para D2 (Versionado real) y D3 (Idempotencia) y D4 (Import Mode)
        if (Schema::hasTable('catalog_versions')) {
            Schema::table('catalog_versions', function (Blueprint $table) {
                if (!Schema::hasColumn('catalog_versions', 'source_file')) {
                    $table->string('source_file', 255)->nullable()->after('version_number');
                }
                if (!Schema::hasColumn('catalog_versions', 'source_file_hash')) {
                    $table->string('source_file_hash', 64)->nullable()->after('source_file');
                }
                if (!Schema::hasColumn('catalog_versions', 'schema_profile_id')) {
                    $table->unsignedBigInteger('schema_profile_id')->nullable()->after('source_file_hash');
                }
                if (!Schema::hasColumn('catalog_versions', 'import_mode')) {
                    $table->string('import_mode', 50)->default('FULL_SNAPSHOT')->after('schema_profile_id');
                }
            });

            try {
                $indexes = collect(DB::select("SHOW INDEX FROM `catalog_versions`"))->pluck('Key_name')->unique();
                if (!$indexes->contains('idx_cv_tenant_file_hash')) {
                    DB::statement("ALTER TABLE `catalog_versions` ADD INDEX `idx_cv_tenant_file_hash` (`tenant_id`, `source_file_hash`)");
                }
            } catch (\Throwable $e) {}
        }

        // 3. Enriquecer products (proyección operacional)
        if (Schema::hasTable('products')) {
            try {
                DB::statement("ALTER TABLE `products` MODIFY COLUMN `base_price` DECIMAL(10,2) NULL DEFAULT NULL");
            } catch (\Throwable $e) {}

            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'price_status')) {
                    $table->string('price_status', 50)->default('CONFIRMED')->after('base_price');
                }
                if (!Schema::hasColumn('products', 'catalog_version_id')) {
                    $table->unsignedBigInteger('catalog_version_id')->nullable()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('canonical_catalog_items') && Schema::hasColumn('canonical_catalog_items', 'price_status')) {
            Schema::table('canonical_catalog_items', function (Blueprint $table) {
                $table->dropColumn('price_status');
            });
        }
        if (Schema::hasTable('catalog_versions')) {
            Schema::table('catalog_versions', function (Blueprint $table) {
                if (Schema::hasColumn('catalog_versions', 'source_file')) $table->dropColumn('source_file');
                if (Schema::hasColumn('catalog_versions', 'source_file_hash')) $table->dropColumn('source_file_hash');
                if (Schema::hasColumn('catalog_versions', 'schema_profile_id')) $table->dropColumn('schema_profile_id');
                if (Schema::hasColumn('catalog_versions', 'import_mode')) $table->dropColumn('import_mode');
            });
        }
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'price_status')) $table->dropColumn('price_status');
                if (Schema::hasColumn('products', 'catalog_version_id')) $table->dropColumn('catalog_version_id');
            });
        }
    }
};
