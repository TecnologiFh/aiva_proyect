# Arquitectura e Plan de Implementación v8.0 - Sprint 8: Inventory & Stock Management (Control de Inventario en Tiempo Real)

## Resumen Ejecutivo

Esta versión **v8.0** del **Sprint 8** establece la especificación técnica del **Inventory & Stock Management**. Elimina sobreventas mediante la separación de existencias (**`current_stock`**, **`reserved_stock`** y **`available_stock` = `current_stock - reserved_stock`**), activa el flujo de reservas automáticas (**`RESERVATION`**) al generarse borradores de pedidos en la IA, audita el valor monetario de mermas e ingresos (**`unit_cost`**, **`total_cost`**), prepara la arquitectura para múltiples sucursales con **`branch_id`**, utiliza **Redis Locks** y **`SELECT FOR UPDATE`** transaccional, y aplica recompilación **Debounced** en Redis para actualizar la base de conocimiento de la IA.

---

## 1. Flujo de Arquitectura del Motor de Inventario (v8.0)

```text
Mensaje del Cliente ──► IA Crea Pedido PENDING_CONFIRMATION
                                  │
                                  ▼
                    ReserveStockForOrderAction
                                  │
                                  ├─► Adquiere Redis Lock ("stock:{tenant}:{branch}:{product}")
                                  ├─► DB Transaction (SELECT FOR UPDATE)
                                  ├─► Incrementa reserved_stock (+cantidad)
                                  └─► Registra movimiento RESERVATION (performed_by_type = AI)
                                            │
                                            ├──────────────────────────┐
                                            ▼                          ▼
                                 [Pedido Cancelado]           [Pedido Confirmado]
                                            │                          │
                                            ▼                          ▼
                                 ReleaseStockReservation      DeductStockAction
                                 (reserved_stock -= cantidad)  • reserved_stock -= cantidad
                                                               • current_stock -= cantidad
                                                               • Movimiento STOCK_OUT (venta)
                                                               • Audita unit_cost y total_cost
                                                                        │
                                                                        ▼
                                                         [current_stock <= minimum_stock]
                                                                        │
                                                                        ├─► Dispara LowStockDetected Event
                                                                        └─► Si current_stock <= 0 ➔ OutOfStockDetected Event
                                                                                 │
                                                                                 ▼
                                                                  Debounced CompileBusinessBrain Job
                                                                  (Actualiza snapshot en Redis 0ms)
```

---

## 2. Componentes Clave del Módulo de Inventario

### 2.1 Enums del Dominio de Inventarios
- **`InventoryMovementType`**: `STOCK_IN`, `STOCK_OUT`, `ADJUSTMENT`, `RESERVATION`.
- **`MovementReason`**: `SALE`, `RETURN`, `EXPIRED`, `WASTE`, `DAMAGE`, `TRANSFER`, `PURCHASE`, `RESERVATION`.
- **`PerformedByType`**: `USER`, `SYSTEM`, `AI`.

### 2.2 Control Concurrente de Existencias
- **`current_stock`**: Total físico real en almacén.
- **`reserved_stock`**: Reservado por órdenes en estado `PENDING_CONFIRMATION` o `AWAITING_ACCEPTANCE`.
- **`available_stock`**: `current_stock - reserved_stock`. (Si `available_stock < cantidad_solicitada`, se deniega la reserva).

---

## 3. Esquema de Base de Datos (Sprint 8 v8.0)

### 3.1 Tabla `inventory_stocks` (Preparada para Sucursales `branch_id`)
```sql
CREATE TABLE inventory_stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL DEFAULT 1, -- Preparado para múltiples sucursales
    product_id BIGINT UNSIGNED NOT NULL,
    current_stock DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    reserved_stock DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    minimum_stock DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    track_inventory TINYINT(1) NOT NULL DEFAULT 1,
    is_out_of_stock TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_branch_product (tenant_id, branch_id, product_id),
    INDEX idx_inventory_stocks_tenant (tenant_id, branch_id, is_out_of_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `inventory_movements` (Auditoría de Kardex Financiero)
```sql
CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    stock_id BIGINT UNSIGNED NOT NULL,
    movement_type VARCHAR(30) NOT NULL, -- STOCK_IN, STOCK_OUT, ADJUSTMENT, RESERVATION
    movement_reason VARCHAR(30) NOT NULL DEFAULT 'SALE', -- SALE, PURCHASE, WASTE, DAMAGE...
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    previous_stock DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    new_stock DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    reference_type VARCHAR(50) NULL, -- ORDER, MANUAL, SYSTEM
    reference_id VARCHAR(100) NULL,
    performed_by_type VARCHAR(30) NOT NULL DEFAULT 'SYSTEM', -- USER, SYSTEM, AI
    performed_by_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_id) REFERENCES inventory_stocks(id) ON DELETE CASCADE,
    INDEX idx_inventory_movements_stock (stock_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: Enums & Domain Events (`Core/`)
- Enums: `InventoryMovementType`, `MovementReason`, `PerformedByType`.
- Domain Events: `StockAdjusted`, `StockDeducted`, `StockReserved`, `StockReservationReleased`, `LowStockDetected`, `OutOfStockDetected`.

### Paso 2: Migraciones & Modelos (`Inventory Module`)
- Migración `2026_08_06_000029_create_inventory_stocks_table.php`.
- Migración `2026_08_06_000030_create_inventory_movements_table.php`.
- Modelos Eloquent `InventoryStock` e `InventoryMovement`.

### Paso 3: Repositorios, Listeners & Debounced Job
- `InventoryStockRepository` (con métodos `reserveStock()`, `releaseReservation()`, `deductStock()`).
- `InventoryMovementRepository`.
- Listener `ReserveStockOnOrderCreated`: Escucha `OrderCreated` y ejecuta `ReserveStockForOrderAction`.
- Listener `DeductStockOnOrderConfirmed`: Escucha `OrderConfirmed` y ejecuta `DeductStockForOrderAction`.
- Job `DebouncedCompileBusinessBrainJob`: Recompila el snapshot de Redis mediante una cola debounced para prevenir sobrecarga.

### Paso 4: Casos de Uso (Actions de la Capa `Application/`)
- **`ReserveStockForOrderAction`**: Incrementa `reserved_stock` (+cantidad) y valida `available_stock`.
- **`DeductStockForOrderAction`**: Descuenta `reserved_stock` y `current_stock`, liquida movimiento `STOCK_OUT` y audita `unit_cost`.
- **`AdjustStockAction`**: Ajuste manual por compra, merma o recuento físico.
- **`ListInventoryAction`**: Reporte de existencias, kardex y alertas de stock bajo.

### Paso 5: Controladores, Rutas & RBAC
- `InventoryController`:
  - `GET /api/v1/inventory` (Consulta de existencias e identificador de stock bajo).
  - `POST /api/v1/inventory/adjust` (Ajuste manual de existencias).
  - `GET /api/v1/inventory/movements` (Kardex e historial de movimientos).

### Paso 6: Suite de Pruebas Automatizadas
- `StockReservationTest`: Verifica el incremento de `reserved_stock` y el cálculo de `available_stock`.
- `StockDeductionTest`: Verifica la libración de reservas y descuento de `current_stock` al confirmar pedido.
- `InventoryKardexTest`: Auditoría financiera de `unit_cost` y `total_cost`.
