<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna normalized_name nullable si no existe
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'normalized_name')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('normalized_name')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'normalized_name')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('normalized_name')->nullable()->after('name');
            });
        }

        // 2. Backfill seguro de registros existentes
        if (Schema::hasTable('categories')) {
            $categories = DB::table('categories')->whereNull('normalized_name')->get();
            foreach ($categories as $cat) {
                $norm = Str::of($cat->name)->lower()->ascii()->trim()->squish()->toString();
                DB::table('categories')->where('id', $cat->id)->update(['normalized_name' => $norm]);
            }
        }

        if (Schema::hasTable('products')) {
            $products = DB::table('products')->whereNull('normalized_name')->get();
            foreach ($products as $prod) {
                $norm = Str::of($prod->name)->lower()->ascii()->trim()->squish()->toString();
                DB::table('products')->where('id', $prod->id)->update(['normalized_name' => $norm]);
            }
        }

        // 3. Crear Índices Únicos Multi-Tenant
        if (Schema::hasTable('categories')) {
            try {
                DB::statement("ALTER TABLE `categories` ADD UNIQUE `categories_tenant_normalized_unique` (`tenant_id`, `normalized_name`)");
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('products')) {
            try {
                DB::statement("ALTER TABLE `products` ADD UNIQUE `products_tenant_cat_norm_unique` (`tenant_id`, `category_id`, `normalized_name`)");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                try {
                    $table->dropUnique('products_tenant_cat_norm_unique');
                } catch (\Throwable $e) {}
                if (Schema::hasColumn('products', 'normalized_name')) {
                    $table->dropColumn('normalized_name');
                }
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                try {
                    $table->dropUnique('categories_tenant_normalized_unique');
                } catch (\Throwable $e) {}
                if (Schema::hasColumn('categories', 'normalized_name')) {
                    $table->dropColumn('normalized_name');
                }
            });
        }
    }
};
