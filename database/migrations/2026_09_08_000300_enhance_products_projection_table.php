<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'compatibility')) {
                    $table->json('compatibility')->nullable()->after('description');
                }
                if (!Schema::hasColumn('products', 'attributes')) {
                    $table->json('attributes')->nullable()->after('compatibility');
                }
                if (!Schema::hasColumn('products', 'stock_status')) {
                    $table->string('stock_status', 50)->default('UNKNOWN')->after('is_available');
                }
            });

            // Asegurar que stock permita NULL (prohibición constitucional de default 0 por ausencia)
            if (Schema::hasColumn('products', 'stock')) {
                try {
                    DB::statement("ALTER TABLE `products` MODIFY COLUMN `stock` INT NULL DEFAULT NULL");
                } catch (\Throwable $e) {}
            }

            // Índice multi-tenant estricto para SKU (tenant-scoped)
            try {
                $indexes = collect(DB::select("SHOW INDEX FROM `products`"))->pluck('Key_name')->unique();
                if (!$indexes->contains('products_tenant_sku_unique') && Schema::hasColumn('products', 'sku')) {
                    DB::statement("ALTER TABLE `products` ADD UNIQUE `products_tenant_sku_unique` (`tenant_id`, `sku`)");
                }
            } catch (\Throwable $e) {
                // Si existen SKUs vacíos o duplicados históricos, crear índice regular de búsqueda
                try {
                    $indexes = collect(DB::select("SHOW INDEX FROM `products`"))->pluck('Key_name')->unique();
                    if (!$indexes->contains('idx_products_tenant_sku')) {
                        DB::statement("ALTER TABLE `products` ADD INDEX `idx_products_tenant_sku` (`tenant_id`, `sku`)");
                    }
                } catch (\Throwable $e2) {}
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'compatibility')) {
                    $table->dropColumn('compatibility');
                }
                if (Schema::hasColumn('products', 'attributes')) {
                    $table->dropColumn('attributes');
                }
                if (Schema::hasColumn('products', 'stock_status')) {
                    $table->dropColumn('stock_status');
                }
            });
        }
    }
};
