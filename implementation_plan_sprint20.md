# Arquitectura e Plan de Implementación v20.0 - Sprint 20: AIVA Business Advisor & Decision Engine (Asesor Prescriptivo de Negocios & Motor Central de Decisiones)

## Resumen Ejecutivo

Esta versión **v20.0** consolida el módulo cúspide de la plataforma: el **AIVA Business Advisor**. Transforma a AIVA en una identidad viva que no solo reporta el pasado, sino que **prescribe el futuro**, calcula el impacto económico en riesgo si se ignoran las recomendaciones y ejecuta planes de acción con respuestas numéricas sencillas (*1, 2, 3 o TODO*) desde WhatsApp.

Incorpora el pipeline **`Datos ➔ Insights ➔ Priorización ➔ Plan de Acción ➔ WhatsApp`**, el **`DecisionEngine`** central, la memoria conversacional **`AdvisorConversationMemory`**, el aprendizaje de preferencias del empresario y el **Simulador de Decisiones What-If**.

---

## 1. Flujo de Arquitectura del AIVA Business Advisor & Decision Engine

```text
Entrada (Cron 8:00 AM / Consulta del Dueño "AIVA ¿cómo vamos?" / "What-If")
                          │
                          ▼
            Pipeline de Inteligencia Prescriptiva
            ┌────────────────────────────────────────────────────────┐
            │ 1. Datos (Sprints 6, 8, 9, 10, 14 & 19)                │
            │ 2. Insights & Impacto Económico en Riesgo              │
            │ 3. Priorización (RecommendationPriority: CRITICAL...)  │
            │ 4. Plan de Acción Interactivo (Opciones 1, 2, 3, TODO) │
            └────────────────────────────────────────────────────────┘
                          │
                          ▼
                   DecisionEngine Central
                          ├─► Ejecuta Compras en Inventory Engine (Sprint 8)
                          ├─► Ejecuta Campañas en Campaign Manager (Sprint 12)
                          └─► Registra Automatización en Automation Engine (Sprint 11)
                                    │
                                    ▼
             Memoria Conversacional & Aprendizaje de Preferencias
             (Recuerda recomendaciones ignoradas & ajusta sugerencias futuras)
```

---

## 2. Componentes Clave del AIVA Business Advisor

### 2.1 Enums del Asesor Prescriptivo
- **`ReportFrequency`**: `DAILY_MORNING`, `WEEKLY_EXECUTIVE`, `MONTHLY_BOARD`, `ON_DEMAND_QUERY`.
- **`RecommendationPriority`**: `CRITICAL`, `HIGH`, `NORMAL`, `LOW`.
- **`DecisionStatus`**: `PROPOSED`, `ACCEPTED`, `REJECTED`, `EXECUTED`, `IGNORED`.

### 2.2 Motor Central de Decisiones (`DecisionEngine`)
- Orquesta y redirige cualquier orden del empresario (*"1"*, *"2"*, *"TODO"*) hacia el módulo correspondiente (**Inventory**, **Campaign**, **Automation**).

### 2.3 Memoria Conversacional del Asesor (`AdvisorConversationMemory`)
- Rastrea qué sugerencias fueron aceptadas o rechazadas previamente para no repetir recomendaciones estériles e intensificar alertas críticas ignoradas.

---

## 3. Esquema de Base de Datos (Sprint 20 v20.0)

### 3.1 Tabla `business_advisor_reports` (Reportes Prescriptivos & Impacto Económico)
```sql
CREATE TABLE business_advisor_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    recipient_phone VARCHAR(30) NOT NULL,
    report_type VARCHAR(30) NOT NULL DEFAULT 'DAILY_MORNING',
    metrics_summary JSON NOT NULL,
    prioritized_recommendations JSON NOT NULL, -- Opciones 1, 2, 3 con impacto económico PEN
    raw_whatsapp_message TEXT NOT NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_advisor_reports (tenant_id, report_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `advisor_decisions` (Historial Auditado de Decisiones del Empresario)
```sql
CREATE TABLE advisor_decisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    report_id BIGINT UNSIGNED NULL,
    action_key VARCHAR(50) NOT NULL, -- OPTION_1, OPTION_2, OPTION_ALL
    recommendation_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACCEPTED', -- ACCEPTED, REJECTED, EXECUTED
    economic_impact_pen DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES business_advisor_reports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular (`app/Modules/Consultant/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── ReportFrequency.php
│   │       ├── RecommendationPriority.php
│   │       └── DecisionStatus.php
│   │
│   ├── Modules/
│   │   └── Consultant/
│   │       ├── Application/
│   │       │   ├── GenerateAdvisorReport/
│   │       │   │   ├── GenerateAdvisorReportAction.php
│   │       │   │   ├── GenerateAdvisorReportInput.php
│   │       │   │   └── GenerateAdvisorReportResult.php
│   │       │   ├── ExecuteDecision/
│   │       │   │   ├── ExecuteDecisionAction.php
│   │       │   │   ├── ExecuteDecisionInput.php
│   │       │   │   └── ExecuteDecisionResult.php
│   │       │   └── SimulateWhatIf/
│   │       │       ├── SimulateWhatIfAction.php
│   │       │       └── SimulateWhatIfInput.php
│   │       ├── Controllers/
│   │       │   └── ConsultantController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000047_create_business_consultant_reports_tables.php
│   │       ├── Models/
│   │       │   ├── BusinessConsultantReport.php
│   │       │   └── AdvisorDecision.php
│   │       ├── Repositories/
│   │       │   └── BusinessConsultantReportRepository.php
│   │       ├── Services/
│   │       │   ├── AIBusinessAdvisorService.php
│   │       │   ├── DecisionEngine.php
│   │       │   ├── AdvisorConversationMemory.php
│   │       │   └── WhatIfSimulatorService.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Consultant Module`)
- Enums: `ReportFrequency`, `RecommendationPriority`, `DecisionStatus`.
- Migración `2026_08_06_000047_create_business_consultant_reports_tables.php`.
- Modelos Eloquent: `BusinessConsultantReport`, `AdvisorDecision`.

### Paso 2: Motor de Decisiones & Servicios de Inteligencia (`Services/`)
- `BusinessConsultantReportRepository`.
- `AIBusinessAdvisorService`: Generación del reporte interactivo con opciones `1, 2, 3, TODO` e impacto económico.
- `DecisionEngine`: Orquestador central de ejecución de decisiones comerciales.
- `AdvisorConversationMemory`: Memoria de recomendaciones ignoradas o aceptadas.
- `WhatIfSimulatorService`: Simulador de escenarios ("¿Qué pasa si subo el precio 5%?").

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`GenerateAdvisorReportAction`**: Genera el informe prescriptivo del AIVA Business Advisor.
- **`ExecuteDecisionAction`**: Ejecuta las decisiones seleccionadas por el empresario.
- **`SimulateWhatIfAction`**: Realiza simulaciones predictivas en tiempo real.

### Paso 4: Controladores, Rutas & RBAC
- `ConsultantController`:
  - `GET /api/v1/consultant/daily-report` (Generar y consultar informe prescriptivo diario).
  - `POST /api/v1/consultant/execute-decision` (Ejecutar opciones `1, 2, 3` o `TODO`).
  - `POST /api/v1/consultant/simulate-what-if` (Simulador de decisiones What-If).
  - `GET /api/v1/consultant/query` (Consulta conversacional on-demand *"AIVA ¿cómo vamos?"*).
- Protegidos mediante `RequirePermission` middleware (`crm:view`, `tenant:manage_settings`).

### Paso 5: Suite de Pruebas Automatizadas
- `DecisionEngineTest`: Ejecución de decisiones sobre Inventory y Campaign Engine.
- `WhatIfSimulatorTest`: Simulación predictiva de precios y márgenes.
- `AdvisorMemoryTest`: Auditoría de memoria conversacional y recomendaciones ignoradas.

---

## User Review Required

> [!IMPORTANT]
> **Cúspide de AIVA SaaS Platform**: Con el Sprint 20 completamos los 20 Sprints planificados en la Hoja de Ruta Maestro ([ADR-008](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-008-architecture-roadmap.md) v3.0), convirtiendo a AIVA en una plataforma inexpugnable e impulsada por IA. Por favor revisa el plan antes de iniciar la ejecución.

---

## Verification Plan

### Automated Tests
1. **`DecisionEngineTest`**: Probar ejecución de opciones `1, 2, 3, TODO`.
2. **`WhatIfSimulatorTest`**: Probar simulación de incremento de precio.
3. **`AdvisorMemoryTest`**: Probar memoria de sugerencias previa.

### Manual Verification
1. Petición HTTP a `/api/v1/consultant/daily-report` consultando el reporte prescriptivo generado para WhatsApp.
