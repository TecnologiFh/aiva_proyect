# Arquitectura e Plan de Implementación v10.0 - Sprint 10: Analytics, Metrics & Business Intelligence (Analítica Ejecutiva, ROI de IA & Métricas)

## Resumen Ejecutivo

Esta versión **v10.0** del **Sprint 10** consolida el módulo **Analytics, Metrics & Business Intelligence**, la capa de inteligencia de negocios de **AIVA**. Incorpora dos tableros estratégicos clave: el **Dashboard de ROI de la IA** (Costo acumulado de consumo de IA vs Ventas Totales Generadas por la IA ➔ **`% ROI`**) y el **Dashboard de Eficiencia Operativa** (Comparativa de tiempo de respuesta: **`IA (2.4s)`** vs **`Humano (5 min)`**).

Además, analiza las métricas comerciales (volumen facturado, ticket promedio, distribución por métodos de pago y modalidades) y mide el **Embudo de Conversión de WhatsApp** (Mensajes ➔ Intenciones ➔ Pedidos ➔ Ventas Cobradas).

---

## 1. Flujo de Arquitectura de Analítica & BI

```text
Transacciones de Pedidos (Sprint 6)         ──► Agregación SQL por Tenant
Transacciones de Pago (Sprint 7)                (Consultas aisladas con tenant_id)
Transacciones de IA (Sprint 4)                           │
Eventos de Mensajería (Sprint 5)                         ▼
                                            Services de Analítica:
                                            • SalesAnalyticsService
                                            • AIPerformanceAnalyticsService
                                            • AIRoiAnalyticsService (Dashboard ROI)
                                            • FunnelAnalyticsService
                                                         │
                                                         ▼
                                         ExecutiveDashboardResult (JSON):
                                         ┌─────────────────────────────────────────────────┐
                                         │ • Ventas Hoy: S/ 1,250.00 (Ticket Prom: S/ 62.50) │
                                         │ • ROI de IA: 1,504% ($12.30 IA ➔ S/ 18,500 Ventas)│
                                         │ • Tiempo Resp: IA 2.4s vs Humano 5 min          │
                                         │ • Embudo Conversión: 150 Msgs ➔ 20 Pedidos      │
                                         └─────────────────────────────────────────────────┘
                                                         │
                                                         ▼
                                         GET /api/v1/analytics/dashboard
```

---

## 2. Indicadores Clave de Rendimiento (KPIs) por Módulo

### 2.1 KPIs de ROI de Inteligencia Artificial (AI ROI)
- **Costo Acumulado de IA (USD)**: Sumatoria del costo en dólares de requests procesados por OpenAI/Gemini.
- **Ventas Totales Generadas por la IA (PEN)**: Sumatoria de pedidos creados desde intenciones de la IA.
- **Porcentaje de ROI de la IA**: `((Ventas_Generadas_PEN - (Costo_IA_USD * TipoCambio)) / (Costo_IA_USD * TipoCambio)) * 100`.
- **Comparativa de Tiempo de Respuesta**:
  - Tiempo promedio de respuesta de la **IA**: ~`2.4 segundos`.
  - Tiempo promedio de respuesta de **Atención Humana**: ~`5.0 minutos`.

### 2.2 KPIs Comerciales & de Ventas
- **Ventas Totales Facturadas** (Diarias, Semanales, Mensuales, Rango de Fechas).
- **Ticket Promedio** (`total_spent / total_orders`).
- **Distribución por Métodos de Pago**: `% Yape/Plin`, `% Efectivo`, `% Tarjeta/Online`.
- **Distribución por Modalidad**: `% Delivery`, `% Recojo (Pickup)`, `% Consumo en Local`.
- **Productos Más Vendidos (Top Products)** por volumen e ingresos.

### 2.3 Embudo de Conversión de WhatsApp (Funnel)
- **Mensajes Recibidos** ➔ **Intenciones de Compra** ➔ **Pedidos Creados (`PENDING_CONFIRMATION`)** ➔ **Pedidos Pagados / Entregados (`PAID` / `DELIVERED`)**.

---

## 3. Estructura del Módulo (`app/Modules/Analytics/`)

```text
AIVA/
├── app/
│   ├── Modules/
│   │   └── Analytics/
│   │       ├── Application/
│   │       │   ├── GetExecutiveDashboard/
│   │       │   │   ├── GetExecutiveDashboardAction.php
│   │       │   │   ├── GetExecutiveDashboardInput.php
│   │       │   │   └── GetExecutiveDashboardResult.php
│   │       │   ├── GetSalesAnalytics/
│   │       │   │   ├── GetSalesAnalyticsAction.php
│   │       │   │   ├── GetSalesAnalyticsInput.php
│   │       │   │   └── GetSalesAnalyticsResult.php
│   │       │   └── GetAIPerformance/
│   │       │       ├── GetAIPerformanceAction.php
│   │       │       ├── GetAIPerformanceInput.php
│   │       │       └── GetAIPerformanceResult.php
│   │       ├── Controllers/
│   │       │   └── AnalyticsController.php
│   │       ├── DTOs/
│   │       │   ├── ExecutiveDashboardDTO.php
│   │       │   ├── SalesMetricsDTO.php
│   │       │   ├── AIPerformanceMetricsDTO.php
│   │       │   └── AIRoiMetricsDTO.php
│   │       ├── Services/
│   │       │   ├── SalesAnalyticsService.php
│   │       │   ├── AIPerformanceAnalyticsService.php
│   │       │   ├── AIRoiAnalyticsService.php
│   │       │   └── FunnelAnalyticsService.php
│   │       └── Routes/api.php
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: DTOs & Services de Analítica (`Analytics Module`)
- DTOs: `ExecutiveDashboardDTO`, `SalesMetricsDTO`, `AIPerformanceMetricsDTO`, `AIRoiMetricsDTO`.
- `SalesAnalyticsService`: Agregaciones de ventas, ticket promedio y distribución de pagos.
- `AIPerformanceAnalyticsService`: Agregaciones de `ai_requests` (tokens, costos, confianza, latencia).
- `AIRoiAnalyticsService`: Cálculo del ROI de la inversión en IA vs Ventas en soles.
- `FunnelAnalyticsService`: Tasa de conversión de mensajería a ventas.

### Paso 2: Casos de Uso (Actions de la Capa `Application/`)
- **`GetExecutiveDashboardAction`**: Consolida la vista ejecutiva del negocio con ROI de IA, ventas y tiempos de respuesta.
- **`GetSalesAnalyticsAction`**: Reporte analítico detallado de ventas por períodos y productos top.
- **`GetAIPerformanceAction`**: Reporte de eficiencia, costos y uso del Motor de IA.

### Paso 3: Controladores, Rutas & RBAC
- `AnalyticsController`:
  - `GET /api/v1/analytics/dashboard` (Dashboard Ejecutivo integral con ROI de IA).
  - `GET /api/v1/analytics/sales` (Analítica de Ventas por fechas).
  - `GET /api/v1/analytics/ai-performance` (Desempeño y costos de la IA).
- Protegidos mediante `RequirePermission` middleware.

### Paso 4: Suite de Pruebas Automatizadas
- `AIRoiAnalyticsTest`: Verifica el cálculo del porcentaje de ROI de la IA (Costo IA USD vs Ventas Generadas PEN).
- `SalesAnalyticsTest`: Verifica la precisión de facturación y ticket promedio.
- `FunnelAnalyticsTest`: Verifica la tasa de conversión del embudo de WhatsApp.
