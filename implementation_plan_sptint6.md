# Arquitectura e Plan de Implementación v6.0 - Sprint 6: Order Engine MVP & Cart Management (Motor de Pedidos Comercial)

## Resumen Ejecutivo

Esta versión **v6.0** del **Sprint 6** establece la especificación técnica del **Order Engine MVP**. Introduce la **Preservación Inmutable Histórica de Pedidos (`OrderSnapshot`)** en formato JSON estructurado dentro de cada ítem de la orden, la correlatividad secuencial aislada por empresa (`order_number` ej. `ORD-2026-0001`), la desacoplamiento por **Domain Events** (`OrderCreated`, `OrderConfirmed`), la propagación del **`X-Correlation-ID`** desde la recepción en WhatsApp hasta la emisión de la orden, y el registro de arquitectura **[ADR-008](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-008-architecture-roadmap.md)** de la hoja de ruta oficial de AIVA.

---

## 1. Arquitectura del Flujo Comercial End-to-End (v6.0)

```text
Cliente escribe en WhatsApp ("Quiero 2 Pizzas Familiares con Extra Queso")
                          │
                          ▼
WhatsApp Webhook (Sprint 5) ──► ProcessWhatsAppWebhookJob (Con X-Correlation-ID)
                          │
                          ▼
AI Engine (Sprint 4) ──► Retorna AIAnalysisResult DTO:
                         • intent: PURCHASE_INTENT
                         • entities: [{"product": "Pizza", "quantity": 2, "options": ["Extra Queso"]}]
                          │
                          ▼
CreateOrderFromAIAction
                          │
                          ├─► Mapea productos y opciones del catálogo (Sprint 3)
                          ├─► Invocación de OrderCalculator (Calcula subtotal, opciones y delivery)
                          ├─► Asigna correlativo por tenant (ORD-2026-0001)
                          ├─► Construye OrderSnapshot JSON inmutable por ítem
                          └─► Registra Order en estado PENDING_CONFIRMATION y emite OrderCreated
                                    │
                                    ▼
SendWhatsAppMessageAction ──► Envía resumen estructurado al cliente vía WhatsApp:
                              "📋 *Resumen de tu Pedido ORD-2026-0001:*
                               • 2x Pizza Familiar + Extra Queso S/ 76.00
                               🛵 Delivery: S/ 5.00
                               💰 *Total: S/ 81.00*
                               ¿Deseas confirmar este pedido?"
```

---

## 2. Componentes Clave del Motor de Pedidos

### 2.1 Order Snapshot Inmutable (`order_items`)
Cada ítem de la orden almacena un JSON inmutable (`options_snapshot`) que congela el precio y los datos del catálogo en el instante de la compra:
```json
{
  "product_uuid": "7a9b1c2d-...",
  "sku": "PIZ-001",
  "product_name": "Pizza Familiar",
  "base_price": 35.00,
  "selected_options": [
    {
      "option_name": "Salsa",
      "value_name": "BBQ",
      "price_modifier": 3.00
    }
  ],
  "calculated_unit_price": 38.00
}
```
*Garantiza que un pedido realizado hoy mantenga su integridad contable 2 años después, aunque el catálogo cambie.*

### 2.2 Calculador de Precios (`OrderCalculator`)
Ubicación: `app/Modules/Orders/Services/OrderCalculator.php`
- Subtotal por ítem: `quantity * (base_price + sum(options_modifiers))`.
- Suma la tarifa de delivery de `business_settings` si `order_type == DELIVERY`.
- Genera los totales finales (`subtotal`, `delivery_fee`, `discount_amount`, `total_amount`).

### 2.3 Generador de Correlativos (`OrderNumberGenerator`)
- Asigna identificadores legibles y secuenciales aislados por empresa (`ORD-2026-0001`).

---

## 3. Esquema de Base de Datos (Sprint 6 v6.0)

### 3.1 Tabla `orders` (Cabecera de Pedido)
```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    order_number VARCHAR(50) NOT NULL, -- ej. ORD-2026-0001
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    delivery_address TEXT NULL,
    order_type VARCHAR(30) NOT NULL DEFAULT 'DELIVERY', -- DELIVERY, PICKUP, DINE_IN
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING_CONFIRMATION',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    payment_method VARCHAR(30) NOT NULL DEFAULT 'CASH',
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    UNIQUE KEY uq_tenant_order_number (tenant_id, order_number),
    INDEX idx_orders_tenant_status (tenant_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `order_items` (Detalle con Snapshot Inmutable)
```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(150) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    options_snapshot JSON NULL, -- OrderSnapshot JSON inmutable
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: Gobernanza & ADR-008 (`docs/adr/`)
- Crear `docs/adr/ADR-008-architecture-roadmap.md` (Registro oficial de la hoja de ruta de AIVA).

### Paso 2: Enums & Domain Events (`Core/`)
- Enums: `OrderStatus`, `OrderType`, `PaymentStatus`, `PaymentMethod`.
- Domain Events: `OrderCreated`, `OrderConfirmed`, `OrderStatusChanged`, `OrderCancelled`.

### Paso 3: Migraciones & Modelos (`Orders Module`)
- Migración `2026_08_06_000023_create_orders_table.php`.
- Migración `2026_08_06_000024_create_order_items_table.php`.
- Modelos Eloquent `Order` y `OrderItem`.

### Paso 4: Repositorios & Calculador de Precios
- `OrderRepository` y `OrderItemRepository`.
- `OrderNumberGenerator`: Generación secuencial de `order_number` por tenant.
- `OrderCalculator`: Cálculo exacto de subtotales, opciones de productos y tarifas de delivery.

### Paso 5: Casos de Uso (Actions de la Capa `Application/`)
- **`CreateOrderFromAIAction`**: Mapea las entidades de la IA a productos del catálogo, construye los `OrderSnapshot` JSON, genera el correlativo, registra la orden en `PENDING_CONFIRMATION` y envía la respuesta formateada por WhatsApp.
- **`ConfirmOrderAction`**: Transiciona el pedido a `CONFIRMED` y notifica al cliente.
- **`UpdateOrderStatusAction`**: Actualiza el estado comercial (`PREPARING`, `READY`, `DELIVERED`, `CANCELLED`).
- **`ListOrdersAction`**: Búsqueda, paginación y filtrado de pedidos.

### Paso 6: Controladores, Rutas & RBAC
- `OrderController`:
  - `POST /api/v1/orders` (Crear pedido).
  - `GET /api/v1/orders` (Listar pedidos).
  - `POST /api/v1/orders/{uuid}/confirm` (Confirmar pedido).
  - `PATCH /api/v1/orders/{uuid}/status` (Actualizar estado).
- Protegidos mediante `RequirePermission` middleware.

### Paso 7: Suite de Pruebas Automatizadas
- `OrderCalculatorTest`: Verifica el cálculo exacto de subtotales, opciones y delivery.
- `CreateOrderFromAITest`: Simula el flujo completo (IA ➔ Entidades ➔ OrderSnapshot ➔ WhatsApp enviado).
- `OrderStatusWorkflowTest`: Transiciones de estado de la orden.
