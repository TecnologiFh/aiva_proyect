# Arquitectura e Plan de Implementación v15.0 - Sprint 15: Marketplace de Integraciones & Open Integration SDK (E-Commerce, Delivery & SUNAT Hub)

## Resumen Ejecutivo

Esta versión **v15.0** eleva el módulo a un **Open Integration SDK & Marketplace**. AIVA actúa como la plataforma central donde no importa por dónde ingresen los pedidos (WhatsApp, Shopify, WooCommerce, Rappi, PedidosYa o Uber Eats): AIVA los **normaliza**, actualiza existencias en tiempo real, emite comprobantes electrónicos con **SUNAT** y sincroniza la operación.

Incorpora normalización en pipeline (**`Webhook ➔ Normalizer ➔ Adapter ➔ Domain Events`**), resiliencia con **Event Replay** (`integration_events`), credenciales cifradas con **`Laravel Encrypted Cast`**, un **Health Monitor** de conectores, un **Retry Center** para reintento de fallos y el **AI Integration Advisor** (la IA sugiere integraciones proactivas al negocio).

---

## 1. Flujo de Arquitectura del Open Integration SDK & Normalizer Pipeline

```text
Webhook / Evento Externo (Shopify / Rappi / SUNAT / WooCommerce)
                          │
                          ▼
            Tabla de Resiliencia: integration_events
            (Persiste payload intacto antes de procesar - Event Replay)
                          │
                          ▼
              Normalizer Pipeline (NormalizerInterface)
              ┌──────────────────────────────────────────────────┐
              │ Convierte payload externo heterogéneo a:        │
              │ IncomingExternalOrderDTO (Modelo Único Interno) │
              └──────────────────────────────────────────────────┘
                          │
                          ▼
             IntegrationAdapterInterface (Integration SDK)
                          │
                          ├─► ShopifyAdapter / WooCommerceAdapter
                          ├─► RappiAdapter / PedidosYaAdapter
                          └─► SunatInvoicingAdapter (Facturación automática)
                                    │
                                    ▼
                     Domain Events (OrderCreated, StockDepleted...)
```

---

## 2. Componentes Clave del Marketplace de Integraciones

### 2.1 Enums del Dominio de Integraciones
- **`IntegrationProvider`**: `SHOPIFY`, `WOOCOMMERCE`, `RAPPI`, `PEDIDOS_YA`, `UBER_EATS`, `SUNAT`, `STRIPE`, `MERCADO_PAGO`.
- **`IntegrationCategory`**: `ECOMMERCE`, `DELIVERY`, `INVOICING`, `PAYMENT_GATEWAY`.
- **`IntegrationStatus`**: `DISCONNECTED`, `CONNECTED`, `SYNCING`, `ERROR`.
- **`EventProcessingStatus`**: `PENDING`, `PROCESSED`, `FAILED`, `RETRYING`.

### 2.2 Normalizador de Eventos (`NormalizerInterface`)
- Convierte estructuras externas variantes a DTOs de dominio unificados (`IncomingExternalOrderDTO`).

### 2.3 Health Monitor & AI Integration Advisor (`AIIntegrationAdvisorService`)
- Monitorea el estado 🟢/🔴 de cada conector y sugiere integraciones faltantes (*"Tus pedidos de Rappi no se están facturando automáticamente. ¿Deseas activar SUNAT?"*).

---

## 3. Esquema de Base de Datos (Sprint 15 v15.0)

### 3.1 Tabla `tenant_integrations` (Credenciales Cifradas & Health Status)
```sql
CREATE TABLE tenant_integrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL, -- SHOPIFY, WOOCOMMERCE, RAPPI, SUNAT...
    category VARCHAR(50) NOT NULL, -- ECOMMERCE, DELIVERY, INVOICING, PAYMENT_GATEWAY
    status VARCHAR(30) NOT NULL DEFAULT 'DISCONNECTED', -- DISCONNECTED, CONNECTED, SYNCING, ERROR
    credentials TEXT NULL, -- Encrypted Cast (Laravel Encryption)
    settings JSON NULL, -- Preferences (auto_sync_orders: true, auto_invoicing: true)
    last_health_check_at TIMESTAMP NULL,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_provider (tenant_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `integration_events` (Event Replay & Auditoría Resiliente)
```sql
CREATE TABLE integration_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    integration_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, PROCESSED, FAILED, RETRYING
    retries_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (integration_id) REFERENCES tenant_integrations(id) ON DELETE CASCADE,
    INDEX idx_integration_events_status (integration_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular del Integration SDK (`app/Modules/Integrations/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── IntegrationProvider.php
│   │       ├── IntegrationCategory.php
│   │       ├── IntegrationStatus.php
│   │       └── EventProcessingStatus.php
│   │
│   ├── Modules/
│   │   └── Integrations/
│   │       ├── SDK/
│   │       │   ├── IntegrationAdapterInterface.php
│   │       │   ├── NormalizerInterface.php
│   │       │   ├── IncomingExternalOrderDTO.php
│   │       │   ├── ShopifyNormalizer.php
│   │       │   └── RappiNormalizer.php
│   │       ├── Adapters/
│   │       │   ├── ShopifyIntegrationAdapter.php
│   │       │   ├── WooCommerceIntegrationAdapter.php
│   │       │   ├── RappiIntegrationAdapter.php
│   │       │   └── SunatInvoicingAdapter.php
│   │       ├── Application/
│   │       │   ├── ConnectIntegration/
│   │       │   │   ├── ConnectIntegrationAction.php
│   │       │   │   ├── ConnectIntegrationInput.php
│   │       │   │   └── ConnectIntegrationResult.php
│   │       │   ├── ProcessIntegrationEvent/
│   │       │   │   ├── ProcessIntegrationEventAction.php
│   │       │   │   └── ProcessIntegrationEventInput.php
│   │       │   └── RetryFailedEvents/
│   │       │       ├── RetryFailedEventsAction.php
│   │       │       └── RetryFailedEventsInput.php
│   │       ├── Controllers/
│   │       │   └── IntegrationController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000042_create_tenant_integrations_tables.php
│   │       ├── Models/
│   │       │   ├── TenantIntegration.php
│   │       │   └── IntegrationEvent.php
│   │       ├── Repositories/
│   │       │   └── TenantIntegrationRepository.php
│   │       ├── Services/
│   │       │   ├── HealthMonitorService.php
│   │       │   └── AIIntegrationAdvisorService.php
│   │       ├── Requests/
│   │       │   └── ConnectIntegrationRequest.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Integrations Module`)
- Enums: `IntegrationProvider`, `IntegrationCategory`, `IntegrationStatus`, `EventProcessingStatus`.
- Migración `2026_08_06_000042_create_tenant_integrations_tables.php`.
- Modelos Eloquent con Cifrado: `TenantIntegration` (con `encrypted` cast), `IntegrationEvent`.

### Paso 2: SDK & Pipeline Normalizador (`SDK/` & `Adapters/`)
- Interfaces: `IntegrationAdapterInterface`, `NormalizerInterface`.
- DTO: `IncomingExternalOrderDTO`.
- Normalizadores: `ShopifyNormalizer`, `RappiNormalizer`.
- Adaptadores: `ShopifyIntegrationAdapter`, `RappiIntegrationAdapter`, `SunatInvoicingAdapter`.

### Paso 3: Servicios de Resiliencia & Asesoría IA (`Services/`)
- `HealthMonitorService`: Verificación de salud y latencia de conectores.
- `AIIntegrationAdvisorService`: Recomendación de integraciones faltantes.

### Paso 4: Casos de Uso (Actions de la Capa `Application/`)
- **`ConnectIntegrationAction`**: Valida y guarda credenciales cifradas (`encrypted` cast).
- **`ProcessIntegrationEventAction`**: Procesa eventos del webhook pasando por `Normalizer` ➔ `Adapter`.
- **`RetryFailedEventsAction`**: Re-ejecución de eventos con fallo en `integration_events` (Retry Center).

### Paso 5: Controladores, Rutas & RBAC
- `IntegrationController`:
  - `GET /api/v1/integrations` (Marketplace con estado de conectores).
  - `POST /api/v1/integrations/connect` (Conectar con credenciales cifradas).
  - `POST /api/v1/integrations/webhook/{provider}` (Recepción resiliente de webhooks).
  - `POST /api/v1/integrations/retry` (Re-procesar eventos fallidos en el Retry Center).
  - `GET /api/v1/integrations/advisor` (Sugerencias del AI Integration Advisor).
- Protegidos mediante `RequirePermission` middleware (`tenant:manage_settings`).

### Paso 6: Suite de Pruebas Automatizadas
- `NormalizerPipelineTest`: Prueba de conversión de JSON heterogéneo a `IncomingExternalOrderDTO`.
- `EventReplayResilienceTest`: Prueba de persistencia y reintento en `integration_events`.
- `EncryptedCredentialsTest`: Cifrado y descifrado seguro de credenciales con Laravel Encryption.
