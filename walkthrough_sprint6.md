# Walkthrough - Sprint 6: Order Engine MVP & Cart Management (Motor de Pedidos Comercial)

Hemos completado al 100% la infraestructura y codificación del **Sprint 6 (Order Engine MVP)** de la plataforma SaaS **AIVA**. Este módulo constituye la **pieza comercial de cierre** que transforma las conversaciones en ventas reales para los negocios, completando el flujo Lean MVP de extremo a extremo: **Empresa (Sprint 1) ➔ Catálogo (Sprint 3) ➔ IA (Sprint 4) ➔ WhatsApp (Sprint 5) ➔ Pedido (Sprint 6)**.

---

## 1. Componentes Construidos en el Sprint 6

### 1.1 Enums del Dominio de Pedidos (`app/Core/Enums/`)
- **`OrderStatus` Enum**: `DRAFT`, `PENDING_CONFIRMATION`, `CONFIRMED`, `PREPARING`, `READY`, `DELIVERED`, `CANCELLED`.
- **`OrderType` Enum**: `DELIVERY` (Envío a domicilio), `PICKUP` (Recojo en tienda), `DINE_IN` (Consumo en local).
- **`PaymentStatus` Enum**: `PENDING`, `PAID`, `FAILED`, `REFUNDED`.
- **`PaymentMethod` Enum**: `CASH`, `YAPE_PLIN`, `CARD`, `ONLINE`.

### 1.2 Base de Datos & Migraciones (`app/Modules/Orders/Database/Migrations/`)
- `2026_08_06_000023_create_orders_table.php`: Cabecera de pedido comercial con `order_number` único por tenant, cliente, montos, dirección y estados.
- `2026_08_06_000024_create_order_items_table.php`: Detalle de ítems con columna JSON `options_snapshot` (**OrderSnapshot inmutable**).

### 1.3 Preservación Inmutable de Precios (`OrderSnapshot`) & Calculador (`OrderCalculator`)
- **`OrderSnapshot`**: Congela `product_uuid`, `sku`, `product_name`, `base_price`, `selected_options` y `calculated_unit_price` en formato JSON por ítem para garantizar la integridad contable histórica.
- **`OrderCalculator`**: Servicio que calcula exactitud de subtotales, modificadores por opciones (*Tamaño, Extras*) y tarifas de envío configuradas en `business_settings`.
- **`OrderNumberGenerator`**: Generador secuencial de correlativos aislados por tenant (`ORD-2026-0001`).

### 1.4 Casos de Uso (Actions de la Capa `Application/`)
- **`CreateOrderFromAIAction`**: Procesa las entidades de la IA (Sprint 4), realiza match con productos del catálogo (Sprint 3), construye la orden en `PENDING_CONFIRMATION` y envía automáticamente un resumen formateado por WhatsApp (Sprint 5).
- **`ConfirmOrderAction`**: Transiciona el pedido a `CONFIRMED` y envía confirmación por WhatsApp.
- **`UpdateOrderStatusAction`**: Cambia el estado del pedido (`PREPARING`, `READY`, `DELIVERED`, `CANCELLED`) con notificación opcional.
- **`ListOrdersAction`**: Búsqueda, paginación y filtrado de pedidos para el panel del negocio.

### 1.5 Controladores, Rutas API & RBAC
- **`OrderController`**:
  - `POST /api/v1/orders` (Creación de pedidos).
  - `GET /api/v1/orders` (Listado y filtrado de pedidos).
  - `POST /api/v1/orders/{uuid}/confirm` (Confirmación de pedido).
  - `PATCH /api/v1/orders/{uuid}/status` (Actualización de estado).
- Protegidos por `RequirePermission` middleware (`order:create`, `order:view`, `order:manage`).

### 1.6 Gobernanza (ADR-008)
- [ADR-008](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-008-architecture-roadmap.md): Registro oficial de la Hoja de Ruta de Arquitectura de AIVA (Sprint 1 al Sprint 10).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/OrderCalculatorTest.php`: Verifica el cálculo exacto de subtotales, opciones de productos y tarifas de delivery.
- `tests/Feature/CreateOrderFromAITest.php`: Verifica el modelo `Order`, atributos y Enums del motor de pedidos.
- `tests/Feature/OrderStatusWorkflowTest.php`: Verifica la transición entre estados del ciclo de vida del pedido (`PENDING_CONFIRMATION` ➔ `CONFIRMED` ➔ `DELIVERED`).

---

## 3. Logro del Flujo Lean MVP Completo (Sprints 1 al 6)

Con la finalización del **Sprint 6**, AIVA cuenta con la **suite completa del flujo comercial de extremo a extremo**:

1. **Sprint 1**: Base Multi-Tenant & Governance.
2. **Sprint 2**: Identity & Roles RBAC Granular.
3. **Sprint 3**: Catálogo Comercial & Snapshot en Redis (`BusinessKnowledgeCompiler`).
4. **Sprint 4**: AI Engine & Detección de Intenciones Comerciales (`AIAnalysisResult`).
5. **Sprint 5**: Canal de WhatsApp Meta Cloud API con Webhooks asíncronos.
6. **Sprint 6**: Pedidos Comerciales, Carrito de Compras & Confirmación por WhatsApp.
