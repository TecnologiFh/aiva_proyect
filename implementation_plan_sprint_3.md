# Arquitectura e Plan de Implementación v3.2 - Sprint 3: Business Brain MVP (Estabilizado)

## Resumen Ejecutivo

Esta versión **v3.2** marca la **estabilización oficial y congelación de la arquitectura del Business Brain MVP** para AIVA. Incorpora el DTO `CompiledBusinessKnowledge`, la distinción entre `schema_version` y `knowledge_version`, el Enum `BusinessType` (`RESTAURANT`, `BAKERY`, `PHARMACY`, `BARBERSHOP`, `CLINIC`, `RETAIL`, `SERVICE`), la estrategia de puntero atómico en Redis (`tenant:{id}:brain:knowledge:current`), el evento `BusinessKnowledgeCompiled` y el registro de decisión [ADR-006](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-006-business-brain-model-stabilization.md).

---

## 1. Componentes de Elite y Compilador (`app/Modules/Business/Services/`)

### 1.1 DTO `CompiledBusinessKnowledge`
Ubicación: `app/Modules/Business/DTOs/CompiledBusinessKnowledge.php`
```php
namespace App\Modules\Business\DTOs;

class CompiledBusinessKnowledge
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $schemaVersion,
        public readonly int $knowledgeVersion,
        public readonly string $hash,
        public readonly array $payload,
        public readonly string $generatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'metadata' => [
                'schema_version' => $this->schemaVersion,
                'knowledge_version' => $this->knowledgeVersion,
                'hash' => $this->hash,
                'generated_at' => $this->generatedAt,
            ],
            'payload' => $this->payload,
        ];
    }
}
```

### 1.2 Estrategia de Puntero Atómico en Redis
- Snapshot versionado: `tenant:{tenant_id}:brain:knowledge:v{version}`
- Puntero de lectura atómica para el Motor de IA (Sprint 4):
  `tenant:{tenant_id}:brain:knowledge:current` ➔ apunta a `v{version}`.

---

## 2. Esquema de Base de Datos Estabilizado (Sprint 3)

### 2.1 Tabla `categories`
```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT NULL,
    search_keywords TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_category_slug (tenant_id, slug, deleted_at),
    INDEX idx_categories_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabla `products`
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT NULL,
    search_keywords TEXT NULL,
    product_type VARCHAR(30) NOT NULL DEFAULT 'PRODUCT', -- Enum ProductType: PRODUCT, SERVICE
    sku VARCHAR(100) NULL,
    base_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    image_path VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE', -- Enum ProductStatus: ACTIVE, INACTIVE, OUT_OF_STOCK
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_tenant_product_slug (tenant_id, slug, deleted_at),
    UNIQUE KEY uq_tenant_sku (tenant_id, sku, deleted_at),
    INDEX idx_products_tenant_category (tenant_id, category_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 Tablas `product_options` y `product_option_values`
```sql
CREATE TABLE product_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    min_selectable INT UNSIGNED NOT NULL DEFAULT 0,
    max_selectable INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_option_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    price_modifier DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 Tabla `business_settings`
```sql
CREATE TABLE business_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(150) NOT NULL,
    business_type VARCHAR(50) NOT NULL DEFAULT 'RESTAURANT', -- Enum BusinessType
    phone VARCHAR(50) NULL,
    whatsapp_phone VARCHAR(50) NULL,
    address TEXT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'America/Lima',
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN', -- Enum Currency
    delivery_enabled TINYINT(1) NOT NULL DEFAULT 1,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    min_order_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    terms_and_conditions TEXT NULL,
    preferences JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.5 Tabla `business_hours`
```sql
CREATE TABLE business_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL, -- 1 = Lunes, 7 = Domingo
    shift_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    open_time TIME NULL,
    close_time TIME NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_day_shift (tenant_id, day_of_week, shift_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Plan de Ejecución Inmediato

### Paso 1: Gobernanza & ADR-006
- Crear `docs/adr/ADR-005-promotions-and-discounts-strategy.md`.
- Crear `docs/adr/ADR-006-business-brain-model-stabilization.md` (Estabilización oficial del modelo para el MVP).

### Paso 2: Enums & DTOs (`Core/` & `Business/`)
- Enums: `BusinessType`, `ProductType`, `ProductStatus`, `Currency`.
- DTO: `CompiledBusinessKnowledge`.
- Domain Events: `ProductCreated`, `ProductUpdated`, `ProductStatusChanged`, `CategoryCreated`, `BusinessSettingsUpdated`, `BusinessKnowledgeCompiled`.

### Paso 3: Migraciones & Modelos (`Business Module`)
- Migraciones: `categories`, `products`, `product_options`, `product_option_values`, `business_settings`, `business_hours`.
- Modelos: `Category`, `Product`, `ProductOption`, `ProductOptionValue`, `BusinessSetting`, `BusinessHour`.

### Paso 4: Repositorios & `BusinessKnowledgeCompiler`
- Repositorios: `CategoryRepository`, `ProductRepository`, `BusinessSettingRepository`, `BusinessHourRepository`.
- Servicio `BusinessKnowledgeCompiler` (`compile()`, `invalidate()`, `getSnapshot()`).

### Paso 5: Actions de la Capa `Application/`
- `CreateCategoryAction`, `ListCategoriesAction`.
- `CreateProductAction` (con opciones), `UpdateProductStatusAction`, `ListProductsAction`.
- `UpdateBusinessSettingsAction`, `SetBusinessHoursAction`.

### Paso 6: Controllers, Rutas API & RBAC
- `CategoryController`, `ProductController`, `BusinessSettingsController`, `BusinessHoursController`.
- Rutas protegidas por `RequirePermission` middleware (`business:view_products`, `business:manage_products`, `tenant:manage_settings`).

### Paso 7: Suite de Pruebas Automatizadas
- `ProductCatalogTest`, `BusinessKnowledgeCompilerTest`, `BusinessHoursTest`.
