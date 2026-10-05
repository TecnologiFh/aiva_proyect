# Arquitectura e Plan de Implementación v11.0 - Sprint 11: Automation Engine (Motor de Reglas & Automatizaciones estilo Zapier)

## Resumen Ejecutivo

Esta versión **v11.0** consolida la arquitectura del **Automation Engine**, el motor de reglas y disparadores automáticos de **AIVA**. Implementa un **árbol lógico de condiciones** (`AND`, `OR`, `NOT`), **acciones diferidas por tiempo** (`delay`), resolución de **variables dinámicas** (`VariableResolver`: `{{customer.name}}`, `{{order.total}}`), biblioteca de **plantillas reutilizables** (`automation_templates`), control de prioridad y conflicto (**`priority`**, **`stop_processing`**), versionado de reglas (**`version`**) y una arquitectura completamente **desacoplada e interconectada** mediante las interfaces **`AutomationActionInterface`** y **`AutomationTriggerInterface`**.

---

## 1. Flujo de Arquitectura del Motor de Automatizaciones (v11.0)

```text
Evento de Dominio o Programado (EVENT / SCHEDULE)
                          │
                          ▼
            AutomationTriggerInterface (OrderCreatedTrigger, LowStockTrigger...)
                          │
                          ▼
              TriggerDispatcher & RuleEvaluator
                          │
                          ├─► Filtra reglas por tenant_id, trigger y orden de priority (100 -> 1)
                          ├─► ConditionEvaluator: Evalúa árbol lógico de condiciones (AND / OR / NOT)
                          └─► Si cumple ➔ ActionExecutor
                                    │
                                    ├─► VariableResolver: Reemplaza {{customer.name}}, {{order.total}}...
                                    ├─► Evalúa Delay: Inmediato (0) o encolado (Job a 7 días / 2 horas)
                                    └─► Ejecuta Pipeline (AutomationActionInterface):
                                          • SendWhatsAppAction (Notificación por WhatsApp)
                                          • AddCustomerTagAction (Etiquetado automático)
                                          • SendAdminAlertAction (Alerta a administradores)
                                          • SendWebhookAction (Integración ERP / Zapier)
                                    │
                                    ├─► Si stop_processing == true ➔ Detiene la evaluación de más reglas
                                    └─► Registrar ejecución en automation_logs
```

---

## 2. Componentes Clave del Motor de Automatizaciones

### 2.1 Enums del Dominio de Automatización
- **`RuleTriggerType`**: `EVENT`, `SCHEDULE`.
- **`LogicOperator`**: `AND`, `OR`, `NOT`.
- **`AutomationActionType`**: `SEND_WHATSAPP`, `ADD_CUSTOMER_TAG`, `SEND_ADMIN_ALERT`, `GENERATE_COUPON`, `SEND_WEBHOOK`.

### 2.2 Abstracciones e Interfaces (`AutomationActionInterface` & `AutomationTriggerInterface`)
- **`AutomationActionInterface`**:
  - `execute(array $params, array $context): bool`
  - `getType(): string`
- **`AutomationTriggerInterface`**:
  - `matches(string $eventName): bool`
  - `extractPayload(object $event): array`
  - `getTriggerName(): string`

### 2.3 Variable Resolver (`VariableResolver`)
- Reemplaza expresiones como `{{customer.name}}`, `{{customer.score}}`, `{{order.number}}`, `{{order.total}}`, `{{stock.current_stock}}` sobre cualquier texto o plantilla.

---

## 3. Esquema de Base de Datos (Sprint 11 v11.0)

### 3.1 Tabla `automation_templates` (Biblioteca de Plantillas Reutilizables)
```sql
CREATE TABLE automation_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'COMMERCIAL', -- COMMERCIAL, ALERT, RETENTION
    content TEXT NOT NULL, -- "Hola {{customer.name}}, tu pedido {{order.number}} está en preparación."
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `automation_rules` (Reglas con Árbol Lógico & Prioridad)
```sql
CREATE TABLE automation_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    trigger_type VARCHAR(30) NOT NULL DEFAULT 'EVENT', -- EVENT, SCHEDULE
    trigger_name VARCHAR(100) NOT NULL, -- ORDER_CREATED, LOW_STOCK_DETECTED...
    cron_expression VARCHAR(50) NULL, -- Parámetro para SCHEDULE (ej. '0 8 * * 1')
    logic_tree JSON NOT NULL, -- Árbol de condiciones: {operator: 'AND', rules: [...]}
    actions JSON NOT NULL, -- Pipeline: [{action_type: 'SEND_WHATSAPP', delay_seconds: 0, params: {...}}]
    priority INT UNSIGNED NOT NULL DEFAULT 50, -- Prioridad de ejecución (100 > 50 > 1)
    stop_processing TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    executions_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_executed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_automation_rules_priority (tenant_id, trigger_name, is_active, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `automation_logs` (Historial & Auditoría de Ejecuciones)
```sql
CREATE TABLE automation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NOT NULL,
    rule_version INT UNSIGNED NOT NULL DEFAULT 1,
    trigger_name VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'SUCCESS', -- SUCCESS, FAILED, SKIPPED
    execution_details JSON NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
    INDEX idx_automation_logs_rule (rule_id, executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular Desacoplada (`app/Modules/Automation/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── RuleTriggerType.php
│   │       ├── LogicOperator.php
│   │       └── AutomationActionType.php
│   │
│   ├── Modules/
│   │   └── Automation/
│   │       ├── Actions/
│   │       │   ├── AutomationActionInterface.php
│   │       │   ├── SendWhatsAppAction.php
│   │       │   ├── AddCustomerTagAction.php
│   │       │   ├── GenerateCouponAction.php
│   │       │   ├── SendAdminAlertAction.php
│   │       │   └── SendWebhookAction.php
│   │       ├── Triggers/
│   │       │   ├── AutomationTriggerInterface.php
│   │       │   ├── OrderCreatedTrigger.php
│   │       │   ├── PaymentApprovedTrigger.php
│   │       │   └── LowStockTrigger.php
│   │       ├── Engine/
│   │       │   ├── RuleEvaluator.php
│   │       │   ├── ActionExecutor.php
│   │       │   ├── ConditionEvaluator.php
│   │       │   ├── VariableResolver.php
│   │       │   └── TriggerDispatcher.php
│   │       ├── Application/
│   │       │   ├── CreateAutomationRule/
│   │       │   │   ├── CreateAutomationRuleAction.php
│   │       │   │   ├── CreateAutomationRuleInput.php
│   │       │   │   └── CreateAutomationRuleResult.php
│   │       │   ├── EvaluateAutomationRules/
│   │       │   │   ├── EvaluateAutomationRulesAction.php
│   │       │   │   └── EvaluateAutomationRulesInput.php
│   │       │   └── ListAutomationRules/
│   │       │       ├── ListAutomationRulesAction.php
│   │       │       ├── ListAutomationRulesInput.php
│   │       │       └── ListAutomationRulesResult.php
│   │       ├── Controllers/
│   │       │   └── AutomationController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       ├── 2026_08_06_000036_create_automation_templates_table.php
│   │       │       ├── 2026_08_06_000037_create_automation_rules_table.php
│   │       │       └── 2026_08_06_000038_create_automation_logs_table.php
│   │       ├── Listeners/
│   │       │   └── SystemEventAutomationDispatcher.php
│   │       ├── Models/
│   │       │   ├── AutomationTemplate.php
│   │       │   ├── AutomationRule.php
│   │       │   └── AutomationLog.php
│   │       ├── Repositories/
│   │       │   └── AutomationRuleRepository.php
│   │       ├── Requests/
│   │       │   └── CreateAutomationRuleRequest.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums, Interfaces & Disparadores (`Automation Module`)
- Enums: `RuleTriggerType`, `LogicOperator`, `AutomationActionType`.
- Interfaces: `AutomationActionInterface`, `AutomationTriggerInterface`.
- Implementaciones de Triggers: `OrderCreatedTrigger`, `PaymentApprovedTrigger`, `LowStockTrigger`.
- Implementaciones de Acciones: `SendWhatsAppAction`, `AddCustomerTagAction`, `SendAdminAlertAction`, `SendWebhookAction`.

### Paso 2: Engine & Evaluadores (`Engine/`)
- `ConditionEvaluator`: Evaluador recursivo de árboles lógicos (`AND`, `OR`, `NOT`).
- `VariableResolver`: Reemplazo dinámico de `{{customer.name}}`, `{{order.total}}`, etc.
- `ActionExecutor`: Pipeline de ejecución de acciones respetando `delay_seconds` y `continue_on_error`.
- `RuleEvaluator` & `TriggerDispatcher`.

### Paso 3: Migraciones & Modelos
- Migración `2026_08_06_000036_create_automation_templates_table.php`.
- Migración `2026_08_06_000037_create_automation_rules_table.php`.
- Migración `2026_08_06_000038_create_automation_logs_table.php`.
- Modelos Eloquent: `AutomationTemplate`, `AutomationRule`, `AutomationLog`.

### Paso 4: Casos de Uso (Actions de la Capa `Application/`)
- **`CreateAutomationRuleAction`**: Registra reglas de automatización con árbol lógico, prioridad y acciones.
- **`EvaluateAutomationRulesAction`**: Evalúa reglas activas y despacha acciones en pipeline.
- **`ListAutomationRulesAction`**: Consulta e historial de auditoría de reglas del tenant.

### Paso 5: Controladores, Rutas & RBAC
- `AutomationController`:
  - `GET /api/v1/automations` (Listar reglas activas, plantillas e historial).
  - `POST /api/v1/automations` (Crear nueva regla con árbol lógico).
  - `PATCH /api/v1/automations/{uuid}/toggle` (Activar / Desactivar regla).

### Paso 6: Suite de Pruebas Automatizadas
- `AutomationLogicTreeTest`: Verifica la evaluación del árbol de condiciones (`AND`, `OR`, `NOT`).
- `VariableResolverTest`: Verifica el reemplazo dinámico de variables en plantillas de mensaje.
- `AutomationActionPipelineTest`: Verifica la ejecución desacoplada de acciones en pipeline.
