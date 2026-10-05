# Plan Maestro de la Fase 4: Excelencia Operacional & Arquitectura de Eventos Enterprise - AIVA SaaS Platform

## Resumen Ejecutivo

Este documento formaliza la **Fase 4: Excelencia Operacional & Arquitectura de Eventos Enterprise** de **AIVA SaaS Platform**. Se centra en el desacoplamiento mediante **Event Bus** y **Event Store**, la gestión financiera de consumos de IA (**Cost Center**), la observabilidad pública (**Status Portal**), la matriz **RBAC + Feature Flags** y las **Pruebas de Carga y Resiliencia** bajo alta concurrencia.

---

## 1. Desacoplamiento por Event Bus & Event Store

```text
                               ┌───────────────────────────┐
                               │       OrderPaid Event     │
                               └─────────────┬─────────────┘
                                             │
      ┌──────────────────┬───────────────────┼───────────────────┬──────────────────┐
      ▼                  ▼                   ▼                   ▼                  ▼
InventoryUpdated  CustomerScoreUpdated  ForecastUpdated  BusinessAdvisorUpdated  CampaignSuggested
```

### 1.1 Esquema de `domain_event_store` (Event Sourcing de Negocio)

```sql
CREATE TABLE domain_event_store (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_name VARCHAR(100) NOT NULL, -- OrderPaid, StockDepleted, CustomerPromoted...
    aggregate_type VARCHAR(100) NOT NULL, -- Order, Customer, Inventory...
    aggregate_id BIGINT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    metadata JSON NULL, -- User IP, Version, Trace ID
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_store (tenant_id, aggregate_type, aggregate_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Cost Center (Gestión de Rentabilidad de IA por Tenant)

Registro auditado del costo financiero en tiempo real generado por cada tenant al consumir APIs de IA, WhatsApp o Voz:

```sql
CREATE TABLE tenant_cost_center_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    service_provider VARCHAR(50) NOT NULL, -- OPENAI, GEMINI, META_WHATSAPP, ELEVENLABS, SUNAT
    operation_type VARCHAR(50) NOT NULL, -- CHAT_COMPLETION, STT, TTS, OCR, SMS
    units_consumed INT UNSIGNED NOT NULL DEFAULT 1, -- Tokens, Segundos, Mensajes
    cost_usd DECIMAL(10, 5) NOT NULL DEFAULT 0.00000,
    cost_pen DECIMAL(10, 5) NOT NULL DEFAULT 0.00000,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_cost_center (tenant_id, service_provider, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Matriz de Acceso Integrada: RBAC + Feature Flags + Plan

```text
Acción Solicitada (ej. GET /api/v1/voice/initiate)
                     │
                     ▼
         1. Verificación RBAC (RequirePermission: 'crm:edit')
                     │
                     ▼
         2. Verificación Feature Flag (TenantFeature: 'VOICE')
                     │
                     ▼
         3. Verificación Límite del Plan del Tenant (Subscription Plan)
                     │
                     ▼
             Acceso Permitido / Denegado (403 Forbidden)
```

---

## 4. Portal de Estado Público (`status.aiva.pe`)

Endpoint público y dashboard visual de disponibilidad en tiempo real del ecosistema:

- 🟢 **WhatsApp Connector (Meta Cloud API)**: 99.98% uptime
- 🟢 **Redis Business & Customer Brain**: 0ms latency
- 🟢 **OpenAI Gateway**: 200 OK
- 🟢 **Google Gemini Fallback**: Active
- 🟢 **SUNAT Facturación Electrónica**: Connected
- 🟢 **AI Voice Employee Engine**: Active
- 🟢 **Marketplace & Integration SDK**: OK

---

## 5. Cuatro Fases Finales de Madurez de AIVA SaaS Platform

```text
┌─────────────────────────────────────────────────────────────────────────┐
│ FASE 1: MVP COMERCIAL (Sprints 1 al 14)                                │
│ Multi-tenant, RBAC, Business Brain, AI Gateway, WhatsApp, Order Engine,│
│ Payments Yape/Plin, Inventario, CRM, BI, Automatizaciones, Loyalty.     │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ FASE 2: OPERACIÓN EMPRESARIAL (Sprints 15 al 20)                        │
│ Integration SDK, SUNAT 🟢, AI Vision, Voice Employee, Live Dashboard,   │
│ Forecast Engine & AIVA Business Advisor Prescriptivo.                   │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ FASE 3: ESCALABILIDAD ENTERPRISE & DEVOPS                               │
│ Auditoría Global (audit_logs), Feature Flags (tenant_features), Horizon,│
│ Prometheus/Grafana, CI/CD, Circuit Breakers & Disaster Recovery.       │
└─────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│ FASE 4: EXCELENCIA OPERACIONAL & EVENT BUS                             │
│ Event Bus desacoplado, Event Store (domain_event_store), Cost Center,   │
│ Status Portal (status.aiva.pe), Matriz RBAC+Flags & Pruebas de Carga.   │
└─────────────────────────────────────────────────────────────────────────┘
```
