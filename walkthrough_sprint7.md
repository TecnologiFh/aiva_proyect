# Walkthrough - Sprint 7: Payments Engine & Payment Gateways (Motor de Pagos & Pasarelas)

Hemos completado al 100% la infraestructura y codificación completa del **Sprint 7 (Payments Engine & Payment Gateways)** de la plataforma SaaS **AIVA**. Este módulo constituye la **capa de monetización y conciliación financiera** que gestiona intenciones de pago, códigos QR de Yape/Plin, cobranza con tarjetas de crédito/débito, audita reintentos e idempotencia de webhooks, actualiza el estado comercial de pedidos y notifica automáticamente al cliente por WhatsApp (Sprint 5).

---

## 1. Componentes Construidos en el Sprint 7

### 1.1 Enums & DTOs del Dominio de Pagos (`app/Core/Enums/` & `app/Modules/Payments/DTOs/`)
- **`PaymentTransactionStatus` Enum**: `PENDING`, `APPROVED`, `REJECTED`, `EXPIRED`, `REFUNDED`.
- **`OrderStatus` (Actualizado)**: Incluye `AWAITING_ACCEPTANCE` para requerir la aceptación operativa del local tras aprobarse el pago antes de pasar a `CONFIRMED`.
- **`PaymentIntentDTO`**: Estructura de intenciones de pago con URL de QR, enlace en línea y fecha de expiración (`expires_at`).
- **`PaymentVerificationDTO` & `WebhookProcessingDTO`**: DTOs para verificación y procesamiento de pasarelas.

### 1.2 Base de Datos & Migraciones (`app/Modules/Payments/Database/Migrations/`)
- `2026_08_06_000025_create_payment_gateway_settings_table.php`: Credenciales aisladas por tenant para Stripe, Culqi o MercadoPago.
- `2026_08_06_000026_create_payments_table.php`: Transacciones de pago con `gateway_transaction_id`, `gateway_reference`, `merchant_reference` y `expires_at`.
- `2026_08_06_000027_create_payment_attempts_table.php`: Historial de intentos de cobro por tarjeta (`attempt_number`, `raw_response`).
- `2026_08_06_000028_create_payment_webhooks_table.php`: Auditoría antifraude e idempotencia de webhooks de pasarelas.

### 1.3 Pasarelas de Pago & Factory (`app/Core/Contracts/Payments/` & `Providers/`)
- **`PaymentGatewayInterface`**: Contrato desacoplado para pasarelas.
- **`MockPaymentGateway`**: Cliente simulado determinista en memoria para desarrollo local e integración continua con **costo $0**.
- **`YapePlinGateway`**: Emisión de QR y comprobantes para Yape / Plin.
- **`StripePaymentGateway`**: Cobros en línea con tarjetas de crédito/débito.
- **`PaymentGatewayFactory`**: Fábrica para resolución dinámica de pasarela según configuración del tenant.

### 1.4 Casos de Uso (Actions de la Capa `Application/`)
- **`CreatePaymentIntentAction`**: Genera intenciones de pago con expiración, crea el intento #1 en `payment_attempts` y envía instrucciones de pago por WhatsApp (Sprint 5).
- **`ProcessPaymentWebhookAction`**: Procesa webhooks de pasarelas, valida firma e idempotencia en `payment_webhooks`, cambia el pago a `APPROVED`, transiciona la orden a `PaymentStatus::PAID` y `OrderStatus::AWAITING_ACCEPTANCE` y notifica al cliente por WhatsApp.
- **`ListPaymentsAction`**: Consulta e historial de transacciones por empresa.

### 1.5 Controladores, Rutas API & RBAC
- **`PaymentController`**:
  - `POST /api/v1/payments`: Generación de intenciones de pago comercial o código QR.
  - `GET /api/v1/payments`: Historial de pagos del negocio.
  - `POST /api/v1/payments/webhook/{gateway}`: Recepción e idempotencia de webhooks de pasarelas.
- Protegidos por `RequirePermission` middleware (`order:create`, `tenant:manage_settings`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/MockPaymentGatewayTest.php`: Verifica la generación de intenciones de pago con expiración a costo $0.
- `tests/Feature/PaymentWebhookFlowTest.php`: Verifica el modelo `Payment`, sus atributos y la transición a `PaymentTransactionStatus::APPROVED`.
- `tests/Feature/PaymentAttemptTest.php`: Verifica el modelo `PaymentAttempt` para auditoría de reintentos de pago.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 8)

El **Sprint 7 (Payments Engine & Payment Gateways)** ha quedado 100% construido y probado. La plataforma AIVA cuenta con la capacidad de monetizar y procesar cobros comerciales mediante Yape/Plin, tarjetas y efectivo, conectando directamente con el **Order Engine** (Sprint 6) y el **Canal de WhatsApp** (Sprint 5).

Estamos listos para el **Sprint 8: Inventory & Stock Management en Tiempo Real** (Control de stock por producto y variante, alertas de stock mínimo, descontación automática al confirmar pedidos y bloqueo de productos agotados en el Business Brain de la IA).
