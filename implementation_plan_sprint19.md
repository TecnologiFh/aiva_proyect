# Arquitectura e Plan de Implementación v19.0 - Sprint 19: Forecast Engine (Motor de Inteligencia Predictiva Multi-Estrategia, Auditoría & Asesoría Narrativa)

## Resumen Ejecutivo

Esta versión **v14.0** del **Sprint 19** eleva el **Forecast Engine** a un **Motor Predictivo Multi-Estrategia de Grado Empresarial**. AIVA no realiza un simple promedio diario: aplica el patrón **`ForecastStrategyInterface`** (Media Móvil Ponderada, Regresión Lineal, Estacionalidad Horaria e IA Predictiva) para proyectar ventas, finanzas, personal necesario y quiebre de existencias.

Cada ejecución se audita en **`forecast_runs`** (midiendo precisión a lo largo del tiempo). Se conecta con el **Automation Engine (Sprint 11)** para notificar proveedores, con el **Campaign Manager (Sprint 12)** para lanzar ofertas reactivas en días de baja demanda proyectada y ofrece **Explicaciones Narrativas por IA** sobre el porqué de cada predicción.

---

## 1. Flujo de Arquitectura del Forecast Engine Multi-Estrategia

```text
Historial Transaccional & Customer Brain (Sprints 6, 8 & 9)
                          │
                          ▼
            ForecastStrategyInterface (Estrategia Adaptativa)
                          ├─► MovingAverageForecast / WeightedAverageForecast
                          ├─► SeasonalityForecast (Demanda Horaria Pico)
                          └─► AIForecastStrategy (Regresión Multi-Variable)
                                    │
                                    ▼
                 Descomposición Modular de Servicios
                 ┌────────────────────────────────────────────────────────┐
                 │ 1. SalesForecastService (Ingresos PEN 7d/30d/90d)      │
                 │ 2. InventoryForecastService (Agotamiento de Insumos)   │
                 │ 3. CustomerForecastService (Churn & Clientes VIP)      │
                 │ 4. FinancialForecastService (Utilidad & Flujo de Caja) │
                 │ 5. DemandForecastService (Personal & Curva Horaria)    │
                 └────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                 Auditoría en forecast_runs & Explicación IA
                 ├─► Score de Confianza Numérico (ej. 93.25%)
                 ├─► Explicación Narrativa ("Proyección +18% por 4 viernes constantes")
                 ├─► Dispara Reglas en Automation Engine (Sprint 11)
                 └─► Dispara Campañas Reactivas en Campaign Manager (Sprint 12)
```

---

## 2. Componentes Clave del Forecast Engine Multi-Estrategia

### 2.1 Enums del Dominio Predictivo
- **`ForecastModelType`**: `SALES_PROJECTION`, `STOCK_DEPLETION`, `DEMAND_SEASONALITY`, `REORDER_RECOMMENDATION`, `STAFFING_PROJECTION`, `FINANCIAL_FLUX`.
- **`ForecastConfidenceLevel`**: `HIGH`, `MEDIUM`, `LOW`.

### 2.2 Patrón Strategy Predictivo (`ForecastStrategyInterface`)
- Estrategias concretas:
  - `MovingAverageForecastStrategy`
  - `SeasonalityForecastStrategy`
  - `AIForecastStrategy`

### 2.3 Servicios Especializados Descompuestos
- **`SalesForecastService`**: Proyecciones de volumen y ventas PEN.
- **`InventoryForecastService`**: Días restantes e insumos específicos a comprar.
- **`CustomerForecastService`**: Retención de clientes y alerta de abandono (Churn).
- **`FinancialForecastService`**: Flujo de caja, costos y utilidad neta.
- **`DemandForecastService`**: Curva horaria de picos y sugerencia de personal (cocineros, repartidores).

---

## 3. Esquema de Base de Datos (Sprint 19 v19.0)

### 3.1 Tabla `forecast_runs` (Auditoría Histórica de Corridas Predictivas)
```sql
CREATE TABLE forecast_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(50) NOT NULL, -- SALES_PROJECTION, STOCK_DEPLETION...
    strategy_used VARCHAR(50) NOT NULL, -- MovingAverage, Seasonality, AI...
    dataset_days_used INT UNSIGNED NOT NULL DEFAULT 30,
    confidence_score DECIMAL(5, 2) NOT NULL DEFAULT 90.00, -- 93.25%
    execution_time_ms INT UNSIGNED NOT NULL DEFAULT 0,
    ai_explanation TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_forecast_runs (tenant_id, model_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `sales_forecasts` (Resultados de Proyecciones & Insumos Sugeridos)
```sql
CREATE TABLE sales_forecasts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    forecast_run_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    forecast_type VARCHAR(50) NOT NULL,
    projected_sales_qty INT UNSIGNED NOT NULL DEFAULT 0,
    projected_revenue_pen DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    days_until_stockout DECIMAL(8, 2) NULL, -- ej. 2.5 días
    recommended_reorder_qty INT UNSIGNED NULL,
    recommended_staffing JSON NULL, -- {cooks: 3, drivers: 2}
    confidence_score DECIMAL(5, 2) NOT NULL DEFAULT 90.00,
    forecast_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (forecast_run_id) REFERENCES forecast_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_sales_forecasts_date (tenant_id, forecast_type, forecast_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular (`app/Modules/Forecast/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── ForecastModelType.php
│   │       └── ForecastConfidenceLevel.php
│   │
│   ├── Modules/
│   │   └── Forecast/
│   │       ├── Strategies/
│   │       │   ├── ForecastStrategyInterface.php
│   │       │   ├── MovingAverageForecastStrategy.php
│   │       │   └── SeasonalityForecastStrategy.php
│   │       ├── Application/
│   │       │   ├── GenerateSalesForecast/
│   │       │   │   ├── GenerateSalesForecastAction.php
│   │       │   │   ├── GenerateSalesForecastInput.php
│   │       │   │   └── GenerateSalesForecastResult.php
│   │       │   └── PredictStockout/
│   │       │       ├── PredictStockoutAction.php
│   │       │       └── PredictStockoutResult.php
│   │       ├── Controllers/
│   │       │   └── ForecastController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000046_create_sales_forecasts_tables.php
│   │       ├── Models/
│   │       │   ├── ForecastRun.php
│   │       │   └── SalesForecast.php
│   │       ├── Repositories/
│   │       │   ├── ForecastRunRepository.php
│   │       │   └── SalesForecastRepository.php
│   │       ├── Services/
│   │       │   ├── SalesForecastService.php
│   │       │   ├── InventoryForecastService.php
│   │       │   ├── CustomerForecastService.php
│   │       │   ├── FinancialForecastService.php
│   │       │   └── DemandForecastService.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Forecast Module`)
- Enums: `ForecastModelType`, `ForecastConfidenceLevel`.
- Migración `2026_08_06_000046_create_sales_forecasts_tables.php`.
- Modelos Eloquent: `ForecastRun`, `SalesForecast`.

### Paso 2: Estrategias Predictivas & Servicios Especializados (`Strategies/` & `Services/`)
- `ForecastStrategyInterface`, `MovingAverageForecastStrategy`, `SeasonalityForecastStrategy`.
- Repositorios: `ForecastRunRepository`, `SalesForecastRepository`.
- Servicios descompuestos: `SalesForecastService`, `InventoryForecastService`, `CustomerForecastService`, `FinancialForecastService`, `DemandForecastService`.

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`GenerateSalesForecastAction`**: Ejecuta corridas predictivas con explicaciones narrativas por IA.
- **`PredictStockoutAction`**: Proyecta quiebre de existencias e insumos requeridos.

### Paso 4: Controladores, Rutas & RBAC
- `ForecastController`:
  - `GET /api/v1/forecast/sales` (Proyección de ventas PEN, insumos y confianza numérico).
  - `GET /api/v1/forecast/stockout` (Predicción de quiebre de stock e insumos).
  - `GET /api/v1/forecast/runs` (Auditoría histórica de ejecuciones predictivas).
- Protegidos mediante `RequirePermission` middleware (`crm:view`).

### Paso 5: Suite de Pruebas Automatizadas
- `ForecastStrategyTest`: Prueba de algoritmos de media móvil y estacionalidad.
- `ForecastRunAuditTest`: Auditoría histórica de corridas predictivas.
- `AIExplainableForecastTest`: Verificación de explicaciones narrativas por IA.

---

## User Review Required

> [!IMPORTANT]
> **Auditoría & Explicabilidad**: Toda predicción guarda en `forecast_runs` los datos utilizados, el tiempo de ejecución y la explicación narrativa generada por la IA para brindar máxima transparencia al empresario. Por favor revisa la propuesta antes de iniciar la ejecución.

---

## Verification Plan

### Automated Tests
1. **`ForecastStrategyTest`**: Probar estrategia de estacionalidad y media móvil.
2. **`ForecastRunAuditTest`**: Probar persistencia de ejecuciones en `forecast_runs`.
3. **`AIExplainableForecastTest`**: Probar generación de explicación narrativa.

### Manual Verification
1. Petición HTTP a `/api/v1/forecast/sales` consultando las proyecciones y explicaciones de la IA.
