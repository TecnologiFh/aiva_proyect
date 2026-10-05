# Arquitectura e Plan de Implementación v7.0 - Sprint 7: Payments Engine & Payment Gateways (Motor de Pagos & Pasarelas)

## Resumen Ejecutivo

Esta versión **v7.0** del **Sprint 7** consolida la arquitectura del **Payments Engine & Payment Gateways**. Introduce la distinción comercial operativa en la orden (**`PAID` ➔ `AWAITING_ACCEPTANCE`** para requerir la aceptación del local antes de pasar a `CONFIRMED`), la tabla **`payment_attempts`** para auditoría de reintentos con tarjetas, la configuración de pasarelas por empresa mediante **`payment_gateway_settings`**, la expiración de intenciones de pago (**`expires_at`**), la auditoría antifraude completa de webhooks (`signature`, `headers`, `payload`) y la abstracción desacoplada de pasarelas (**`MockPaymentGateway`**, **`YapePlinGateway`**, **`StripePaymentGateway`**).

---

## 1. Flujo Comercial Operativo de Pagos (v7.0)

```text
Orden Creada (Sprint 6 - Estado PENDING_CONFIRMATION)
                          │
                          ▼
CreatePaymentIntentAction
                          │
                          ├─► Consulta credenciales de pasarela en payment_gateway_settings por tenant
                          ├─► Invocación de PaymentGatewayInterface (Mock / YapePlin / Stripe)
                          ├─► Asigna fecha de expiración (expires_at)
                          ├─► Registra Payment (Status: PENDING) y PaymentAttempt #1
                          └─► Envía QR / Enlace de pago al cliente por WhatsApp (Sprint 5)
                                    │
                                    ▼
Procesador de Pagos / Webhook ──► POST /api/v1/payments/webhook/{gateway}
                                    │
                                    ├─► Registra Webhook completo (payload, headers, signature)
                                    ├─► Valida Idempotencia y Firma de Seguridad
                                    ├─► Actualiza Payment a APPROVED
                                    └─► Emite Domain Event PaymentApproved
                                              │
                                              ▼
                    Listeners Desacoplados (Event Bus)
                                              │
                                              ├─► Listener 1: UpdateOrderPaymentStatus
                                              │    • Order PaymentStatus ➔ PAID
                                              │    • Order Status ➔ AWAITING_ACCEPTANCE (Esperando aceptación del local)
                                              │
                                              └─► Listener 2: NotifyCustomerViaWhatsApp
                                                   • "💳 *¡Pago Recibido! (S/ 81.00)*
                                                      Tu pedido ORD-2026-0001 está esperando la confirmación final de nuestro local."
```

---

## 2. Componentes Clave del Motor de Pagos

### 2.1 Enums del Dominio de Pagos
- **`PaymentTransactionStatus`**: `PENDING`, `APPROVED`, `REJECTED`, `EXPIRED`, `REFUNDED`.
- **`PaymentGatewayProvider`**: `MOCK`, `YAPE_PLIN`, `STRIPE`, `CULQI`, `MERCADOPAGO`, `CASH_ON_DELIVERY`.
- **`OrderStatus` (Actualizado)**: `DRAFT`, `PENDING_CONFIRMATION`, `AWAITING_ACCEPTANCE`, `CONFIRMED`, `PREPARING`, `READY`, `DELIVERED`, `CANCELLED`.

### 2.2 Abstracción `PaymentGatewayInterface`
Ubicación: `app/Core/Contracts/Payments/PaymentGatewayInterface.php`
- Interface desacoplada con soporte para pasarelas reales y **`MockPaymentGateway`** (desarrollo y pruebas $0).

---

## 3. Esquema de Base de Datos (Sprint 7 v7.0)

### 3.1 Tabla `payment_gateway_settings` (Credenciales por Tenant)
```sql
CREATE TABLE payment_gateway_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(50) NOT NULL, -- MOCK, YAPE_PLIN, STRIPE, CULQI
    public_key TEXT NULL,
    secret_key TEXT NULL,
    merchant_id VARCHAR(150) NULL,
    environment VARCHAR(30) NOT NULL DEFAULT 'SANDBOX', -- SANDBOX, PRODUCTION
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_gateway (tenant_id, gateway)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `payments` (Cabecera de Transacción)
```sql
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    gateway_transaction_id VARCHAR(150) NULL,
    gateway_reference VARCHAR(150) NULL,
    merchant_reference VARCHAR(150) NULL,
    authorization_code VARCHAR(50) NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    qr_code_url VARCHAR(255) NULL,
    payment_link VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payments_tenant_status (tenant_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `payment_attempts` (Historial de Intentos)
```sql
CREATE TABLE payment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    error_code VARCHAR(100) NULL,
    error_message TEXT NULL,
    raw_response JSON NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Tabla `payment_webhooks` (Auditoría Antifraude & Idempotencia)
```sql
CREATE TABLE payment_webhooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(150) NOT NULL UNIQUE,
    gateway VARCHAR(50) NOT NULL,
    signature VARCHAR(255) NULL,
    headers JSON NULL,
    payload JSON NOT NULL,
    processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: Enums & Domain Events (`Core/`)
- Actualizar `OrderStatus` Enum agregando `AWAITING_ACCEPTANCE`.
- Enum `PaymentTransactionStatus`: `PENDING`, `APPROVED`, `REJECTED`, `EXPIRED`, `REFUNDED`.
- Interfaces: `PaymentGatewayInterface`.
- DTOs: `PaymentIntentDTO`, `PaymentVerificationDTO`, `WebhookProcessingDTO`.
- Domain Events: `PaymentIntentCreated`, `PaymentPending`, `PaymentApproved`, `PaymentRejected`, `PaymentExpired`, `PaymentRefunded`.

### Paso 2: Migraciones & Modelos (`Payments Module`)
- Migración `2026_08_06_000025_create_payment_gateway_settings_table.php`.
- Migración `2026_08_06_000026_create_payments_table.php`.
- Migración `2026_08_06_000027_create_payment_attempts_table.php`.
- Migración `2026_08_06_000028_create_payment_webhooks_table.php`.
- Modelos Eloquent: `PaymentGatewaySetting`, `Payment`, `PaymentAttempt`, `PaymentWebhook`.

### Paso 3: Gateways, Repositorios & Listeners
- `PaymentRepository` y `PaymentGatewaySettingRepository`.
- `MockPaymentGateway` (costo $0 para desarrollo y tests).
- `YapePlinGateway` (generación de QR y confirmación por comprobante).
- `StripePaymentGateway` (cobros en línea).
- `PaymentGatewayFactory` (resolución dinámica de pasarela por tenant).
- Listeners desacoplados para `PaymentApproved` Event.

### Paso 4: Casos de Uso (Actions de la Capa `Application/`)
- **`CreatePaymentIntentAction`**: Genera intenciones de pago con `expires_at`, registra `PaymentAttempt` #1 y envía instrucciones por WhatsApp (Sprint 5).
- **`ProcessPaymentWebhookAction`**: Recibe webhooks, guarda firma y headers en `payment_webhooks`, cambia el pago a `APPROVED` y despacha `PaymentApproved`.
- **`ListPaymentsAction`**: Consulta e historial de pagos e intentos.

### Paso 5: Controladores, Rutas & RBAC
- `PaymentController`:
  - `POST /api/v1/payments` (Generar intención de pago).
  - `GET /api/v1/payments` (Historial de transacciones).
  - `POST /api/v1/payments/webhook/{gateway}` (Recepción de webhooks).

### Paso 6: Suite de Pruebas Automatizadas
- `MockPaymentGatewayTest`: Verifica la generación de intenciones de pago con expiración a costo $0.
- `PaymentWebhookFlowTest`: Simula el webhook de aprobación, verificando la transición de la orden a `PAID` ➔ `AWAITING_ACCEPTANCE` y la notificación por WhatsApp.
- `PaymentAttemptTest`: Verifica el registro de múltiples intentos de pago fallidos y aprobados.
