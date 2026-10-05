<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Asegurar índices compuestos únicos (tenant_id, id) en tablas referenciadas
        $this->ensureCompositeUniqueIndex('products', 'uq_products_tenant_id', ['tenant_id', 'id']);
        $this->ensureCompositeUniqueIndex('commercial_media', 'uq_cm_tenant_id', ['tenant_id', 'id']);
        $this->ensureCompositeUniqueIndex('product_variants', 'uq_pv_tenant_id', ['tenant_id', 'id']);
        $this->ensureCompositeUniqueIndex('raw_menu_evidences', 'uq_rme_tenant_id', ['tenant_id', 'id']);
        $this->ensureCompositeUniqueIndex('catalog_versions', 'uq_cv_tenant_id', ['tenant_id', 'id']);

        // 2. Foreign Keys Compuestos para product_visual_references (C10 DB-Level Enforcement)
        if (Schema::hasTable('product_visual_references')) {
            $this->ensureForeignKey('product_visual_references', 'fk_pvr_tenant_media', ['tenant_id', 'commercial_media_id'], 'commercial_media', ['tenant_id', 'id']);
            $this->ensureForeignKey('product_visual_references', 'fk_pvr_tenant_evidence', ['tenant_id', 'evidence_id'], 'raw_menu_evidences', ['tenant_id', 'id']);
            $this->ensureForeignKey('product_visual_references', 'fk_pvr_tenant_product', ['tenant_id', 'product_id'], 'products', ['tenant_id', 'id']);
            $this->ensureForeignKey('product_visual_references', 'fk_pvr_tenant_variant', ['tenant_id', 'variant_id'], 'product_variants', ['tenant_id', 'id']);
        }

        // 3. Foreign Keys Compuestos para product_variants (C10: variant -> product)
        if (Schema::hasTable('product_variants')) {
            $this->ensureForeignKey('product_variants', 'fk_pv_tenant_product', ['tenant_id', 'product_id'], 'products', ['tenant_id', 'id']);
        }

        // 4. Foreign Keys Compuestos para catalog_version_media
        if (Schema::hasTable('catalog_version_media')) {
            $this->ensureForeignKey('catalog_version_media', 'fk_cvm_tenant_version', ['tenant_id', 'catalog_version_id'], 'catalog_versions', ['tenant_id', 'id']);
            $this->ensureForeignKey('catalog_version_media', 'fk_cvm_tenant_media', ['tenant_id', 'commercial_media_id'], 'commercial_media', ['tenant_id', 'id']);
        }

        // 5. C13: Exactamente Un Catálogo Activo y Publicado por Tenant en MySQL
        if (Schema::hasTable('catalog_versions')) {
            try {
                if (!Schema::hasColumn('catalog_versions', 'active_published_tenant_id')) {
                    DB::statement("ALTER TABLE catalog_versions ADD COLUMN active_published_tenant_id BIGINT GENERATED ALWAYS AS (IF(status = 'PUBLISHED' AND is_active = 1, tenant_id, NULL)) VIRTUAL");
                }
                $indexes = DB::select("SHOW INDEX FROM catalog_versions WHERE Key_name = 'uq_single_active_catalog'");
                if (empty($indexes)) {
                    DB::statement("ALTER TABLE catalog_versions ADD UNIQUE INDEX uq_single_active_catalog (active_published_tenant_id)");
                }
            } catch (\Throwable $e) {
                // Si la versión de MySQL o MariaDB no soporta virtual indexes, continuar
            }
        }
    }

    private function ensureCompositeUniqueIndex(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
            if (empty($indexes)) {
                $cols = implode(',', $columns);
                DB::statement("ALTER TABLE {$table} ADD UNIQUE INDEX {$indexName} ({$cols})");
            }
        } catch (\Throwable $e) {}
    }

    private function ensureForeignKey(string $table, string $constraintName, array $localCols, string $refTable, array $refCols): void
    {
        if (!Schema::hasTable($table) || !Schema::hasTable($refTable)) {
            return;
        }
        try {
            $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND CONSTRAINT_NAME = '{$constraintName}'");
            if (empty($fks)) {
                $loc = implode(',', $localCols);
                $ref = implode(',', $refCols);
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} FOREIGN KEY ({$loc}) REFERENCES {$refTable}({$ref}) ON DELETE RESTRICT");
            }
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Rollback opcional
    }
};
