# Walkthrough - Sprint 8: Inventory & Stock Management (Control de Inventario en Tiempo Real)

Hemos completado al 100% la infraestructura y codificación completa del **Sprint 8 (Inventory & Stock Management)** de la plataforma SaaS **AIVA**. Este módulo constituye el **motor de existencias físicas y trazabilidad de Kardex financiero** que elimina sobreventas mediante la separación de existencias (**`current_stock`**, **`reserved_stock`** y **`available_stock` = `current_stock - reserved_stock`**), activa la reserva automática por la IA (**`RESERVATION`**), audita el valor monetario de mermas e ingresos (**`unit_cost`**, **`total_cost`**), prepara la arquitectura para sucursales futuras (**`branch_id`**) y aplica recompilación **Debounced** en Redis para actualizar la base de conocimiento del **Business Brain**.

---

## 1. Componentes Construidos en el Sprint 8

### 1.1 Enums & DTOs del Dominio de Inventario (`app/Core/Enums/`)
- **`InventoryMovementType` Enum**: `STOCK_IN`, `STOCK_OUT`, `ADJUSTMENT`, `RESERVATION`.
- **`MovementReason` Enum**: `SALE`, `PURCHASE`, `WASTE`, `DAMAGE`, `EXPIRED`, `RETURN`, `TRANSFER`, `RESERVATION`.
- **`PerformedByType` Enum**: `USER`, `SYSTEM`, `AI`.
- **Accesor `available_stock`**: Retorna automáticamente `max(0, current_stock - reserved_stock)` en el modelo `InventoryStock`.

### 1.2 Base de Datos & Migraciones (`app/Modules/Inventory/Database/Migrations/`)
- `2026_08_06_000029_create_inventory_stocks_table.php`: Existencias por producto preparadas para sucursales (`branch_id = 1`) con `current_stock`, `reserved_stock`, `minimum_stock` e `is_out_of_stock`.
- `2026_08_06_000030_create_inventory_movements_table.php`: Kardex de auditoría financiera con `unit_cost`, `total_cost`, `movement_reason` y trazabilidad de autoría (`performed_by_type`).

### 1.3 Control Concurrente, Transacciones & Recompilación Debounced
- **Lock Transaccional**: Consultas con `lockForUpdate()` dentro de `DB::transaction()` al reservar o descontar existencias.
- **`DebouncedCompileBusinessBrainJob`**: Job encolado que invalida y recompila el snapshot de la IA en Redis (`0ms` latencia) sin saturar el servidor ante múltiples compras simultáneas.

### 1.4 Casos de Uso (Actions de la Capa `Application/`)
- **`ReserveStockForOrderAction`**: Incrementa `reserved_stock` (+cantidad) cuando la IA genera un pedido borrador, emitiendo `StockReserved`.
- **`DeductStockForOrderAction`**: Descuenta `reserved_stock` y `current_stock` al confirmar un pedido, registra el movimiento `STOCK_OUT` (venta) y recompila la caché de la IA si el producto llega a 0.
- **`AdjustStockAction`**: Registro de ingresos por compras, mermas o recuentos físicos con costos unitarios y totales.
- **`ListInventoryAction`**: Paginación, alertas de stock mínimo y consulta de Kardex por tenant.

### 1.5 Listeners Desacoplados (Event Bus)
- `ReserveStockOnOrderCreated`: Escucha `OrderCreated` (Sprint 6) y ejecuta `ReserveStockForOrderAction`.
- `DeductStockOnOrderConfirmed`: Escucha `OrderConfirmed` (Sprint 6/7) y ejecuta `DeductStockForOrderAction`.

### 1.6 Controladores, Rutas API & RBAC
- **`InventoryController`**:
  - `GET /api/v1/inventory`: Lista de existencias con flags `is_low_stock` y `is_out_of_stock`.
  - `POST /api/v1/inventory/adjust`: Ajuste manual de existencias y costos.
  - `GET /api/v1/inventory/movements`: Kardex e historial de auditoría por empresa.
- Protegidos por `RequirePermission` middleware (`inventory:view`, `inventory:adjust`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/StockReservationTest.php`: Verifica el cálculo del stock disponible `available_stock = current_stock - reserved_stock`.
- `tests/Feature/StockDeductionTest.php`: Verifica la detección del flag `is_out_of_stock` cuando el stock llega a 0.
- `tests/Feature/InventoryKardexTest.php`: Auditoría de costos (`unit_cost`, `total_cost`), motivos (`MovementReason`) y autoría (`PerformedByType::AI`).

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 9)

El **Sprint 8 (Inventory & Stock Management)** ha quedado 100% construido y probado. La plataforma AIVA cuenta con control de existencias anti-sobreventa, auditoría de Kardex financiero, reservas automáticas por IA y recompilación debounced de Redis.

Estamos listos para el **Sprint 9: CRM & Segmentación Comercial de Clientes** (Perfiles de clientes, preferencias permanentes `customer_memories`, historial de compras por WhatsApp, etiquetas y segmentación para campañas de marketing).
