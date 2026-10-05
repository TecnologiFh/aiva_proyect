# Arquitectura e Plan de Implementación - Sprint 12: Campaign Manager (Gestor de Campañas Segmentadas por WhatsApp)

## Resumen Ejecutivo

El **Sprint 12** construye el **Campaign Manager**, el módulo de difusión comercial masiva y segmentada por WhatsApp de **AIVA**. Conecta la inteligencia del **AI CRM & Customer Brain** (Sprint 9) y la inyección de plantillas con variables dinámicas de **VariableResolver** (Sprint 11) con el canal de mensajería de WhatsApp (Sprint 5), procesando envíos en lotes asíncronos mediante **Queue Jobs (`ProcessCampaignJob`)** para evitar sobrepasar los límites de la API de Meta y garantizar tasa de entrega óptima.

---

## 1. Flujo de Arquitectura del Gestor de Campañas

```text
Configuración de Campaña (Filtros del AI CRM + Plantilla)
                          │
                          ▼
            CreateCampaignAction (Estado: DRAFT / SCHEDULED)
                          │
                          ▼
             LaunchCampaignAction
                          │
                          ├─► Resuelve destinatarios según segmentación del CRM:
                          │     • VIP (customer_score >= 80)
                          │     • Inactivos (>30 días sin comprar)
                          │     • Preferencia de Categoría (ej. Pizzas)
                          │
                          ├─► Registra registros en campaign_recipients
                          └─► Despacha Job Asíncrono: ProcessCampaignJob (Queue)
                                    │
                                    ▼
                      ProcessCampaignJob Batch Execution:
                      ┌──────────────────────────────────────────────┐
                      │ 1. Resuelve variables {{customer.name}}      │
                      │ 2. Envía mensaje mediante WhatsAppClient     │
                      │ 3. Actualiza estado (SENT / DELIVERED)       │
                      │ 4. Incrementa contadores de la campaña        │
                      └──────────────────────────────────────────────┘
```

---

## 2. Componentes Clave del Gestor de Campañas

### 2.1 Enums del Dominio de Campañas
- **`CampaignStatus`**: `DRAFT`, `SCHEDULED`, `PROCESSING`, `COMPLETED`, `PAUSED`, `CANCELLED`.
- **`CampaignTargetSegment`**: `ALL_CUSTOMERS`, `VIP_CUSTOMERS`, `INACTIVE_30D`, `HIGH_SPENDERS`, `CUSTOM_TAGS`.
- **`RecipientStatus`**: `PENDING`, `SENT`, `DELIVERED`, `FAILED`.

### 2.2 Motor de Segmentación Inteligente del AI CRM
- Segmentación por atributos del `CustomerProfile` y `Customer`:
  - `stage == 'VIP'` o `customer_score >= 80`.
  - `days_since_last_purchase > 30`.
  - `favorite_category == 'Pizzas'`.
  - Etiquetas específicas (`tags`).

---

## 3. Esquema de Base de Datos (Sprint 12)

### 3.1 Tabla `campaigns` (Campañas de Mensajería Masiva)
```sql
CREATE TABLE campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    template_id BIGINT UNSIGNED NULL,
    message_content TEXT NOT NULL,
    target_segment VARCHAR(50) NOT NULL DEFAULT 'ALL_CUSTOMERS',
    segment_filters JSON NULL, -- [{field: 'customer.score', operator: '>=', value: 80}]
    scheduled_at TIMESTAMP NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT', -- DRAFT, SCHEDULED, PROCESSING, COMPLETED, PAUSED, CANCELLED
    total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES automation_templates(id) ON DELETE SET NULL,
    INDEX idx_campaigns_status (tenant_id, status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `campaign_recipients` (Auditoría de Destinatarios de la Campaña)
```sql
CREATE TABLE campaign_recipients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(30) NOT NULL,
    variables_snapshot JSON NULL, -- {name: 'Luis Fiestas', total: 86.50}
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING', -- PENDING, SENT, DELIVERED, FAILED
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_campaign_recipients_status (campaign_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura del Módulo (`app/Modules/Campaigns/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── CampaignStatus.php
│   │       ├── CampaignTargetSegment.php
│   │       └── RecipientStatus.php
│   │
│   ├── Modules/
│   │   └── Campaigns/
│   │       ├── Application/
│   │       │   ├── CreateCampaign/
│   │       │   │   ├── CreateCampaignAction.php
│   │       │   │   ├── CreateCampaignInput.php
│   │       │   │   └── CreateCampaignResult.php
│   │       │   ├── LaunchCampaign/
│   │       │   │   ├── LaunchCampaignAction.php
│   │       │   │   └── LaunchCampaignInput.php
│   │       │   ├── GetCampaignAnalytics/
│   │       │   │   ├── GetCampaignAnalyticsAction.php
│   │       │   │   └── GetCampaignAnalyticsResult.php
│   │       │   └── ListCampaigns/
│   │       │       ├── ListCampaignsAction.php
│   │       │       ├── ListCampaignsInput.php
│   │       │       └── ListCampaignsResult.php
│   │       ├── Controllers/
│   │       │   └── CampaignController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000039_create_campaigns_and_recipients_tables.php
│   │       ├── Jobs/
│   │       │   └── ProcessCampaignBatchJob.php
│   │       ├── Models/
│   │       │   ├── Campaign.php
│   │       │   └── CampaignRecipient.php
│   │       ├── Repositories/
│   │       │   └── CampaignRepository.php
│   │       ├── Requests/
│   │       │   └── CreateCampaignRequest.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Campaigns Module`)
- Enums: `CampaignStatus`, `CampaignTargetSegment`, `RecipientStatus`.
- Migración `2026_08_06_000039_create_campaigns_and_recipients_tables.php`.
- Modelos Eloquent: `Campaign`, `CampaignRecipient`.

### Paso 2: Repositorio & Processing Job (`Jobs/`)
- `CampaignRepository`.
- `ProcessCampaignBatchJob`: Queue Job para procesamiento asíncrono por lotes de mensajería masiva con inyección de variables por cliente.

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`CreateCampaignAction`**: Creación de campaña con filtros de segmentación del CRM.
- **`LaunchCampaignAction`**: Resuelve destinatarios y encola la ejecución en segundo plano.
- **`GetCampaignAnalyticsAction`**: Reporte de efectividad y métricas de entrega en tiempo real.
- **`ListCampaignsAction`**: Gestión y listado de campañas.

### Paso 4: Controladores, Rutas & RBAC
- `CampaignController`:
  - `GET /api/v1/campaigns` (Listar campañas del tenant).
  - `POST /api/v1/campaigns` (Crear borrador o campaña programada).
  - `POST /api/v1/campaigns/{uuid}/launch` (Lanzar disparo masivo de campaña).
  - `GET /api/v1/campaigns/{uuid}/analytics` (Analítica de efectividad de la campaña).
- Protegidos mediante `RequirePermission` middleware (`crm:edit`).

### Paso 5: Suite de Pruebas Automatizadas
- `CampaignSegmentationTest`: Verifica la resolución de destinatarios según el segmento del CRM (VIP, Inactivos).
- `CampaignJobBatchExecutionTest`: Verifica el despacho asíncrono en lotes e inyección de plantillas de mensaje.
- `CampaignAnalyticsTest`: Auditoría de entrega en tiempo real.

---

## User Review Required

> [!IMPORTANT]
> **Despacho Asíncrono en Lotes (Queue Jobs)**: Todas las campañas se procesan en segundo plano en lotes controlados para garantizar que el servidor no se sobrecargue y que se respeten las políticas de la API de Meta Cloud. Por favor revisa la propuesta antes de proceder a la ejecución.

---

## Verification Plan

### Automated Tests
1. **`CampaignSegmentationTest`**: Crear clientes VIP e inactivos y verificar la selección de destinatarios.
2. **`CampaignJobBatchExecutionTest`**: Ejecutar `ProcessCampaignBatchJob` y verificar el envío por `WhatsAppClient`.
3. **`CampaignAnalyticsTest`**: Consultar las estadísticas de entrega y fallos.

### Manual Verification
1. Petición HTTP a `/api/v1/campaigns` registrando una campaña y lanzando el disparo masivo.
