# Implementation Plan — P2.12: AIVA Intelligence & Business Control

> **FASE:** P2.12 — AIVA Intelligence & Business Control  
> **ESTADO:** PROPUESTA APROBADA CON CAMBIOS Y LISTA PARA EJECUCIÓN  
> **PRINCIPIO:** Lectura, Agregación, Análisis e Inteligencia sin mutación operacional directa. Delegación autónoma postergada a P2.13.

---

## User Review Required

> [!IMPORTANT]
> **Performance & CQRS Architecture**: Para evitar que la apertura del Dashboard degrade la base de datos OLTP con cientos de `SUM()`, `COUNT()` y `JOIN`s pesados, se implementará un modelo de lectura desacoplado con tablas analíticas de agregación (`analytics_daily_metrics`, `analytics_product_daily`, `analytics_customer_metrics`).

> [!CRITICAL]
> **Daily Closing Inmutabilidad**: Una vez cerrado el día (`status = CLOSED`), el registro en `daily_closings` queda inmutable con un hash SHA-256 de integridad. Cualquier ajuste posterior registrará audit de forma obligatoria en `daily_closing_adjustments`.

> [!TIP]
> **Ask AIVA Zero-Hallucination**: Las consultas en lenguaje natural de la IA se procesan a través de `AskAivaIntentResolver` que ejecuta funciones métricas determinísticas en PHP/MySQL, entregando un `GroundingDataPayload` al LLM. Si los datos son insuficientes, responderá `INSUFFICIENT_DATA` sin inventar números.

---

## Proposed Changes

### Componente 1: Modelo de Datos Analítico & Migraciones MySQL

#### [NEW] [2026_08_28_000001_create_p212_analytics_and_closings_tables.php](file:///C:/xampp/htdocs/AIVA/database/migrations/2026_08_28_000001_create_p212_analytics_and_closings_tables.php)
- Crear tabla `analytics_daily_metrics` (`tenant_id`, `date`, `gross_sales`, `net_sales`, `orders_count`, `paid_amount`, `pending_amount`, `cancelled_amount`, `avg_ticket`).
- Crear tabla `analytics_product_daily` (`tenant_id`, `date`, `product_id`, `units_sold`, `revenue`, `cogs`, `margin`).
- Crear tabla `analytics_customer_metrics` (`tenant_id`, `customer_id`, `total_orders`, `total_spent`, `rfm_recency`, `rfm_frequency`, `rfm_monetary`, `rfm_segment`).
- Crear tabla `daily_closings` (`tenant_id`, `closing_date`, `status`, `expected_cash`, `declared_cash`, `difference_amount`, `declared_by_user_id`, `closing_hash`, `closed_at`).
- Crear tabla `daily_closing_adjustments` (`tenant_id`, `daily_closing_id`, `adjustment_amount`, `reason`, `user_id`, `correlation_id`).
- Crear tabla `report_exports` (`tenant_id`, `report_type`, `format`, `download_token`, `expires_at`).
- Crear tabla `scheduled_reports` y `report_deliveries` (Control de idempotencia de reportes por WhatsApp).

---

### Componente 2: Engine de Métricas y Servicio CQRS

#### [NEW] [MetricEngineService.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Analytics/Services/MetricEngineService.php)
- Servicio determinístico para consultar ventas, productos, clientes y pagos con soporte para períodos comparativos (`TODAY`, `YESTERDAY`, `THIS_WEEK`, `THIS_MONTH`, `LAST_MONTH`, `CUSTOM`).

#### [NEW] [DailyClosingService.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Analytics/Services/DailyClosingService.php)
- Ejecución atómica de cierre diario (`CERRAR DÍA`), cálculo de efectivo esperado vs declarado, generación de Checksum SHA-256 e inmutabilidad con `daily_closing_adjustments`.

---

### Componente 3: Ask AIVA & AI Business Insights Zero-Hallucination

#### [NEW] [AskAivaIntentResolverService.php](file:///C:/xampp/htdocs/AIVA/app/Modules/AI/Services/AskAivaIntentResolverService.php)
- Intencionador de lenguaje natural que traduce preguntas del propietario (*"¿Cuánto vendí hoy?"*, *"¿Cuál es mi producto más vendido?"*) en llamadas a `MetricEngineService`, generando `GroundingDataPayload` sin alucinación.

---

### Componente 4: Report Center (PDF / XLSX / CSV)

#### [NEW] [ReportExportService.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Analytics/Services/ReportExportService.php)
- Generación de reportes ejecutivos en PDF, Excel (XLSX) y CSV con marcas de agua, firmas de integridad, `correlation_id` y tokens de descarga seguros con vencimiento de 15 minutos.

---

## Verification Plan

### Automated Tests
- Ejecución de la suite máster de 30 BI-GATEs (`GET /api/v1/analytics/master-certification-v1`).
- Verificación de aislamiento multi-tenant en consultas analíticas.
- Verificación de inmutabilidad en cierres diarios y cálculo de RFM.

### Manual Verification
- Probar la ejecución de **CERRAR DÍA** en el panel de control.
- Probar preguntas financieras en **Ask AIVA** verificando que las respuestas coincidan 100% con MySQL.
- Descargar reportes ejecutivos en PDF y Excel desde el Report Center.
