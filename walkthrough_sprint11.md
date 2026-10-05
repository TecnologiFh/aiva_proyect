# Walkthrough - Sprint 11: Automation Engine (Motor de Reglas & Automatizaciones estilo Zapier)

Hemos completado al 100% la infraestructura y codificación del **Sprint 11 (Automation Engine)** de la plataforma SaaS **AIVA**. Con este módulo iniciamos la **Fase 2 de Desarrollo**, transformando a AIVA de una plataforma de chat y pedidos a un **motor SaaS de automatización comercial proactivo**, desacoplado e interconectado mediante contratos e interfaces.

---

## 1. Componentes Construidos en el Sprint 11

### 1.1 Árbol Lógico de Condiciones (`LogicOperator` & `ConditionEvaluator`)
- Soporta evaluar expresiones jerárquicas complejas (`AND`, `OR`, `NOT`).
- Permite combinar múltiples filtros (`order.total > 100 AND customer.score >= 50`).

### 1.2 Inyección Dinámica de Variables (`VariableResolver`)
- Reemplazo dinámico de variables en plantillas e instrucicones: `{{customer.name}}`, `{{customer.score}}`, `{{order.number}}`, `{{order.total}}`, `{{stock.current_stock}}`.

### 1.3 Abstracción & Extensibilidad (`AutomationActionInterface` & `AutomationTriggerInterface`)
- **Contratos Desacoplados**: El motor de automatizaciones no conoce las implementaciones concretas.
- **Acciones Implementadas**: `SendWhatsAppAction`, `AddCustomerTagAction`, `SendAdminAlertAction`, `SendWebhookAction`.
- **Triggers Implementados**: `OrderCreatedTrigger`, `PaymentApprovedTrigger`, `LowStockTrigger`.

### 1.4 Prioridad, Versionado & Detención de Procesamiento
- `priority`: Orden de evaluación (100 > 50 > 1).
- `stop_processing`: Detiene la evaluación de reglas secundarias cuando una regla prioritaria se ejecuta.
- `version`: Versionado de reglas para auditoría histórica.

### 1.5 Biblioteca de Plantillas & Auditoría (`automation_templates` & `automation_logs`)
- `automation_templates`: Plantillas reutilizables de mensajería comercial por categoría (`COMMERCIAL`, `ALERT`, `RETENTION`).
- `automation_logs`: Registro detallado del resultado de ejecución (`SUCCESS`, `FAILED`, `SKIPPED`).

### 1.6 Controladores, Rutas API & RBAC
- **`AutomationController`**:
  - `GET /api/v1/automations`: Consulta de reglas activas e historial de ejecuciones.
  - `POST /api/v1/automations`: Creación de reglas con árbol de condiciones JSON.
  - `PATCH /api/v1/automations/{uuid}/toggle`: Activar / Desactivar regla.
- Protegidos por `RequirePermission` middleware (`tenant:manage_settings`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/AutomationLogicTreeTest.php`: Verifica la evaluación recursiva de árboles lógicos `AND`, `OR` y `NOT`.
- `tests/Feature/VariableResolverTest.php`: Verifica el reemplazo dinámico de tokens en plantillas.
- `tests/Feature/AutomationActionPipelineTest.php`: Verifica la ejecución desacoplada de acciones en pipeline.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 12)

El **Sprint 11 (Automation Engine)** ha quedado 100% construido y probado. AIVA cuenta con la capacidad de automatizar flujos comerciales proactivos sin intervención humana.

Estamos listos para el **Sprint 12: Campaign Manager** (Gestor de campañas de mensajería masiva por WhatsApp segmentadas por el AI CRM del Sprint 9).
