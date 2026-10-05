# Walkthrough - Sprint 3: Business Brain MVP (Conocimiento Comercial del Negocio)

Hemos completado al 100% la construcción e infraestructura completa del **Sprint 3 (Business Brain MVP)** de la plataforma SaaS **AIVA**. Este módulo constituye la **base de conocimiento comercial optimizada** que alimentará al Motor de Inteligencia Artificial (Sprint 4) y al Conector de WhatsApp (Sprint 5).

---

## 1. Componentes Construidos en el Sprint 3

### 1.1 Enums & DTOs (`Core/Enums/` & `Business/DTOs/`)
- **`BusinessType` Enum**: `RESTAURANT`, `BAKERY`, `PHARMACY`, `BARBERSHOP`, `CLINIC`, `RETAIL`, `SERVICE`.
- **`ProductType` Enum**: `PRODUCT` vs `SERVICE`.
- **`ProductStatus` Enum**: `ACTIVE`, `INACTIVE`, `OUT_OF_STOCK`.
- **`Currency` Enum**: `PEN`, `USD`, `EUR`, `MXN`, `COP`, `CLP`.
- **`CompiledBusinessKnowledge` DTO**: DTO estandarizado con metadatos (`tenant_id`, `schema_version`, `knowledge_version`, `hash`, `generated_at`) y el payload compilado.

### 1.2 Base de Datos & Migraciones (`app/Modules/Business/Database/Migrations/`)
- `2026_08_05_000012_create_categories_table.php`: Categorías con `description` y `search_keywords`.
- `2026_08_05_000013_create_products_table.php`: Productos con `base_price`, `search_keywords`, `image_path` y `sku` único por tenant.
- `2026_08_05_000014_create_product_options_table.php`: Opciones de productos (*Tamaños, Sabores, Toppings*).
- `2026_08_05_000015_create_product_option_values_table.php`: Valores de opciones con modificadores de precio.
- `2026_08_05_000016_create_business_settings_table.php`: Configuración comercial con teléfono, WhatsApp, dirección, delivery y `preferences JSON`.
- `2026_08_05_000017_create_business_hours_table.php`: Horarios de atención por turnos diarios (`shift_number`).

### 1.3 `BusinessKnowledgeCompiler` (Compilador de IA para Redis)
- Compila el catálogo completo, categorías, opciones, horarios por turnos y configuración del negocio.
- Guarda el snapshot versionado en Redis (`tenant:{id}:brain:knowledge:v{version}`) y actualiza el puntero de lectura atómica para la IA (`tenant:{id}:brain:knowledge:current`).
- Despacha el Domain Event `BusinessKnowledgeCompiled`.

### 1.4 Casos de Uso (Actions de la Capa `Application/`)
- **Categorías**: `CreateCategoryAction`, `ListCategoriesAction`.
- **Productos**: `CreateProductAction` (soporte para opciones y valores), `UpdateProductStatusAction`, `ListProductsAction`.
- **Configuración y Horarios**: `UpdateBusinessSettingsAction`, `SetBusinessHoursAction`.
- Toda mutación re-compila automáticamente el snapshot del negocio en Redis.

### 1.5 Controladores, Rutas API & RBAC
- **`CategoryController`**: `GET /api/v1/business/categories`, `POST /api/v1/business/categories`
- **`ProductController`**: `GET /api/v1/business/products`, `POST /api/v1/business/products`, `PATCH /api/v1/business/products/{uuid}/status`
- **`BusinessSettingsController`**: `GET /api/v1/business/settings`, `PUT /api/v1/business/settings`
- **`BusinessHoursController`**: `GET /api/v1/business/hours`, `POST /api/v1/business/hours`
- Rutas protegidas mediante `RequirePermission` middleware (`business:view_products`, `business:manage_products`, `tenant:manage_settings`).

### 1.6 Gobernanza (ADRs)
- [ADR-005](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-005-promotions-and-discounts-strategy.md): Estrategia de Promociones y Descuentos Futuros.
- [ADR-006](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-006-business-brain-model-stabilization.md): Estabilización del Modelo del Business Brain MVP.

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/ProductCatalogTest.php`: Verifica el modelo `Product`, atributos, casts y Enums.
- `tests/Feature/BusinessKnowledgeCompilerTest.php`: Verifica la estructura del DTO `CompiledBusinessKnowledge` y formateo JSON.
- `tests/Feature/BusinessHoursTest.php`: Verifica el modelo `BusinessHour` y el soporte para múltiples turnos por día.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 4)

El **Sprint 3 (Business Brain MVP)** ha quedado 100% construido y probado. La base de datos y la caché de Redis cuentan con la estructura óptima para dar paso al **Sprint 4: AI Engine MVP** (Detección de intenciones, conexión con proveedores LLM y procesamiento de consultas comerciales sobre el snapshot de Redis).
