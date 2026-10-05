# Arquitectura e Plan de Implementación v9.0 - Sprint 9: AI CRM & Customer Brain (Cerebro del Cliente en Redis)

## Resumen Ejecutivo

Esta versión **v9.0** transforma el **Sprint 9** de un CRM tradicional a un **AI CRM & Customer Brain**. Introduce la **omnicanalidad** (`customer_identities` para WhatsApp, Instagram, Telegram, Web), la **separación de perfil comercial** (`customer_profiles` con métricas avanzadas: Ticket Promedio, CLV, Tasa de Cancelación, Score 0-100), la **trazabilidad cronológica** (`customer_timeline`), **System vs User Tags**, geolocalización en direcciones y la innovación central: **`CustomerBrainCompiler`**.

El **Customer Brain** compila un snapshot ultra-rápido en Redis (**`tenant:{tenant_id}:customer:{customer_id}:brain`**) con los hábitos, preferencias, resumen generado por la IA y productos favoritos del cliente. En combinación con el **Business Brain** (Sprint 3), el **PromptContextBuilder** (Sprint 4) inyecta ambos cerebros en milisegundos a la IA, permitiéndole responder por WhatsApp (Sprint 5) con hiper-personalización (*"Hola Luis, la última vez pediste Pizza Familiar sin cebolla. ¿Deseas repetir ese pedido?"*) a **costo $0 latencia de base de datos**.

---

## 1. Flujo de Arquitectura del AI CRM & Customer Brain

```text
Mensaje Omnicanal (WhatsApp/Instagram/Web) ──► CustomerResolverService (Omnicanal)
                                                      │
                                                      ▼
                                       Carga desde Redis (0ms latencia):
                                       • Business Brain (Sprint 3)
                                       • Customer Brain (Sprint 9 - Hábitos, Resumen IA, Memorias)
                                                      │
                                                      ▼
                                           PromptContextBuilder (Sprint 4)
                                                      │
                                                      ▼
                       La IA responde con Hiper-Personalización Instantánea
                                                      │
                                                      ▼
                       Transacción / Pedido Entregado (Sprint 6/7)
                                                      │
                                                      ├─► Registra hito en customer_timeline
                                                      ├─► Recalcula Métricas en customer_profiles
                                                      │    (Orders 30d/90d, CLV, Ticket Promedio, Score 0-100)
                                                      ├─► Actualiza customer_stage (NEW ➔ ACTIVE ➔ LOYAL ➔ VIP)
                                                      └─► Recompila CustomerBrainCompiler (Redis 0ms)
```

---

## 2. Componentes Clave del AI CRM

### 2.1 Enums del Dominio CRM
- **`CustomerStage`**: `NEW`, `ACTIVE`, `LOYAL`, `VIP`, `INACTIVE`, `LOST`.
- **`ChannelType`**: `WHATSAPP`, `INSTAGRAM`, `MESSENGER`, `TELEGRAM`, `WEB`.
- **`TagType`**: `SYSTEM` (Ej. `VIP`, `RISK_OF_CHURN`, `HIGH_SPENDER`), `USER` (Ej. `Amigo`, `Empresa`).

### 2.2 Customer Brain Compiler (`CustomerBrainCompiler`)
- Genera el JSON en Redis `tenant:{tenant_id}:customer:{customer_id}:brain`:
```json
{
  "customer": {
    "uuid": "cust-1234",
    "name": "Luis",
    "stage": "VIP",
    "score": 92,
    "favorite_category": "Pizzas",
    "favorite_products": ["Pizza Familiar", "Coca Cola 1.5L"],
    "average_ticket": 86.50,
    "preferred_payment": "YAPE_PLIN",
    "ai_summary": "Cliente VIP muy amable. Siempre pide Pizza Familiar sin cebolla los viernes por la noche.",
    "memories": [
      {"preference": "Sin cebolla", "confidence": 0.96, "times_detected": 12}
    ]
  }
}
```

---

## 3. Esquema de Base de Datos (Sprint 9 v9.0)

### 3.1 Tabla `customers` & `customer_identities` (Omnicanalidad)
```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL DEFAULT 'Cliente',
    email VARCHAR(150) NULL,
    customer_stage VARCHAR(30) NOT NULL DEFAULT 'NEW', -- NEW, ACTIVE, LOYAL, VIP, INACTIVE, LOST
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_identities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(30) NOT NULL, -- WHATSAPP, INSTAGRAM, TELEGRAM, WEB
    identifier VARCHAR(150) NOT NULL, -- +51999888777, @luis
    created_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_channel_identifier (tenant_id, channel, identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `customer_profiles` (Métricas Inteligentes & Score 0-100)
```sql
CREATE TABLE customer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
    total_orders_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    average_ticket DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    clv DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    cancellation_rate DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    customer_score INT UNSIGNED NOT NULL DEFAULT 50, -- Score 0 a 100
    orders_30d INT UNSIGNED NOT NULL DEFAULT 0,
    orders_90d INT UNSIGNED NOT NULL DEFAULT 0,
    favorite_category VARCHAR(100) NULL,
    preferred_payment VARCHAR(30) NULL,
    preferred_delivery VARCHAR(30) NULL,
    ai_summary TEXT NULL,
    last_order_at TIMESTAMP NULL,
    last_interaction_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `customer_timeline` (Línea de Tiempo Cronológica)
```sql
CREATE TABLE customer_timeline (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL, -- MESSAGE, ORDER_CREATED, PAYMENT_APPROVED, CLAIM, COUPON_SENT
    description TEXT NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_timeline (customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Tabla `customer_addresses` (Geolocalización & Zonas)
```sql
CREATE TABLE customer_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(50) NOT NULL DEFAULT 'Casa',
    address TEXT NOT NULL,
    reference VARCHAR(255) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    delivery_zone VARCHAR(100) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 Tablas `customer_tags`, `customer_tag_map` & `customer_marketing_preferences`
```sql
CREATE TABLE customer_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    tag_type VARCHAR(30) NOT NULL DEFAULT 'USER', -- SYSTEM, USER
    color VARCHAR(10) NOT NULL DEFAULT '#6c757d',
    created_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_tag_name (tenant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_tag_map (
    customer_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (customer_id, tag_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES customer_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_marketing_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
    accepts_promotions TINYINT(1) NOT NULL DEFAULT 1,
    preferred_channel VARCHAR(30) NOT NULL DEFAULT 'WHATSAPP',
    last_campaign_id VARCHAR(100) NULL,
    last_coupon_code VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: Enums & DTOs (`Core/` & `CRM/`)
- Enums: `CustomerStage`, `ChannelType`, `TagType`.
- DTOs: `CustomerBrainSnapshotDTO`.

### Paso 2: Migraciones & Modelos (`CRM Module`)
- Migración `2026_08_06_000031_create_customers_and_identities_tables.php`.
- Migración `2026_08_06_000032_create_customer_profiles_table.php`.
- Migración `2026_08_06_000033_create_customer_timeline_table.php`.
- Migración `2026_08_06_000034_create_customer_addresses_geoloc_table.php`.
- Migración `2026_08_06_000035_create_customer_tags_and_marketing_tables.php`.
- Modelos Eloquent: `Customer`, `CustomerIdentity`, `CustomerProfile`, `CustomerTimeline`, `CustomerAddress`, `CustomerTag`, `CustomerMarketingPreference`.

### Paso 3: Customer Brain Compiler (`Services/`)
- `CustomerBrainCompiler`: Servicio que construye y almacena en Redis el snapshot inteligente del cliente (`tenant:{tenant_id}:customer:{customer_id}:brain`).

### Paso 4: Casos de Uso (Actions de la Capa `Application/`)
- **`ResolveCustomerAction`**: Resuelve la identidad omnicanal del cliente e inyecta su Customer Brain.
- **`RecordTimelineEventAction`**: Registra hitos en la línea de tiempo del cliente.
- **`UpdateCustomerMetricsAction`**: Recalcula métricas RFM, Score 0-100 y CustomerStage (`NEW` ➔ `ACTIVE` ➔ `LOYAL` ➔ `VIP`).
- **`ListCustomersAction`**: Consulta, búsqueda y segmentación avanzada por tenant.

### Paso 5: Controladores, Rutas & RBAC
- `CustomerController`:
  - `GET /api/v1/customers` (Listado y segmentación inteligente).
  - `GET /api/v1/customers/{uuid}` (Perfil 360° con timeline, memorias y Customer Brain).
  - `POST /api/v1/customers/{uuid}/addresses` (Agregar dirección con geolocalización).
  - `POST /api/v1/customers/{uuid}/tags` (Asignar etiquetas).

### Paso 6: Suite de Pruebas Automatizadas
- `CustomerBrainCompilerTest`: Verifica la compilación en Redis del Customer Brain.
- `CustomerMetricsAndScoreTest`: Verifica el cálculo del score (0-100) y transición a stage `VIP`.
- `CustomerTimelineTest`: Auditoría de la línea de tiempo del cliente.
