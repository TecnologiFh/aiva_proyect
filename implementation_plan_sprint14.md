# Arquitectura e Plan de Implementación v14.0 - Sprint 14: Loyalty Engine (Programa de Fidelización Proactivo con IA: Puntos, Cashback, Referidos & Gift Cards)

## Resumen Ejecutivo

Esta versión **v14.0** del **Sprint 14** consolida el **Loyalty Engine**, el motor de fidelización, retención y recompensas comerciales de **AIVA**. Incorpora **Fidelización Proactiva impulsada por IA**: cuando el motor detecta un cliente inactivo (`> 30 a 40 días` sin comprar), genera automáticamente un cupón dinámico del 10% y despacha una oferta personalizada por WhatsApp. Si el cliente efectúa una compra, el sistema desactiva la oferta y premia con saldo Cashback y Puntos acumulables.

Se sincroniza en tiempo real con el **Customer Brain en Redis** (Sprint 9) para ofrecer atención instantánea a `0ms` de latencia de BD.

---

## 1. Flujo de Arquitectura del Motor de Fidelización Proactivo

```text
Detección de Inactividad (AI CRM - 40 días sin pedir)
                          │
                          ▼
            AILoyaltyProactiveEngine
                          │
                          ├─► Genera oferta/cupón automático (ej. 10% descuento)
                          ├─► Despacha WhatsApp mediante SendMessageAction (Sprint 5)
                          └─► Registra estado PENDING en loyalty_transactions
                                    │
                                    ▼
                Cliente responde y realiza compra (PaymentApproved Event):
                ┌────────────────────────────────────────────────────────┐
                │ 1. Aplica cupón de la oferta y liquida la transacción  │
                │ 2. Otorga Cashback (5%) y Puntos (1 Pts por S/ 10)     │
                │ 3. Actualiza Nivel VIP (BRONZE -> DIAMOND)              │
                │ 4. Recompila Customer Brain en Redis (0ms)              │
                └────────────────────────────────────────────────────────┘
```

---

## 2. Componentes Clave del Motor de Fidelización

### 2.1 Enums del Dominio de Lealtad
- **`LoyaltyTier`**: `BRONZE`, `SILVER`, `GOLD`, `PLATINUM`, `DIAMOND`.
- **`LoyaltyTransactionType`**: `EARN_POINTS`, `REDEEM_POINTS`, `CASHBACK_EARN`, `CASHBACK_REDEEM`, `REFERRAL_BONUS`, `GIFT_CARD_REDEEM`, `EXPIRED_POINTS`.
- **`GiftCardStatus`**: `ACTIVE`, `REDEEMED`, `EXPIRED`, `CANCELLED`.

### 2.2 Motor de Fidelización Proactiva por IA (`AILoyaltyProactiveEngine`)
- Monitorea periódicamente clientes en riesgo de churn (`days_since_last_purchase >= 30`).
- Genera cupones temporales y los despacha por WhatsApp integrándose con el `VariableResolver` (Sprint 11).

---

## 3. Esquema de Base de Datos (Sprint 14 v14.0)

### 3.1 Tabla `loyalty_accounts` (Cuentas de Puntos, Cashback & Nivel VIP)
```sql
CREATE TABLE loyalty_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
    tier VARCHAR(30) NOT NULL DEFAULT 'BRONZE', -- BRONZE, SILVER, GOLD, PLATINUM, DIAMOND
    points_balance INT UNSIGNED NOT NULL DEFAULT 0,
    cashback_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    referral_code VARCHAR(30) NOT NULL UNIQUE,
    total_earned_points INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_loyalty_tier (tenant_id, tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `loyalty_transactions` (Historial Auditado de Movimientos de Lealtad)
```sql
CREATE TABLE loyalty_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    loyalty_account_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    transaction_type VARCHAR(50) NOT NULL, -- EARN_POINTS, REDEEM_POINTS, CASHBACK_EARN, REFERRAL_BONUS...
    points INT NOT NULL DEFAULT 0,
    cashback_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loyalty_account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_loyalty_tx_account (loyalty_account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `gift_cards` (Tarjetas de Regalo Digitales)
```sql
CREATE TABLE gift_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    initial_balance DECIMAL(10, 2) NOT NULL,
    current_balance DECIMAL(10, 2) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE', -- ACTIVE, REDEEMED, EXPIRED, CANCELLED
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_gift_cards_code (tenant_id, code, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular (`app/Modules/Loyalty/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── LoyaltyTier.php
│   │       ├── LoyaltyTransactionType.php
│   │       └── GiftCardStatus.php
│   │
│   ├── Modules/
│   │   └── Loyalty/
│   │       ├── Application/
│   │       │   ├── EarnPointsForOrder/
│   │       │   │   ├── EarnPointsForOrderAction.php
│   │       │   │   ├── EarnPointsForOrderInput.php
│   │       │   │   └── EarnPointsForOrderResult.php
│   │       │   ├── RedeemPointsForOrder/
│   │       │   │   ├── RedeemPointsForOrderAction.php
│   │       │   │   ├── RedeemPointsForOrderInput.php
│   │       │   │   └── RedeemPointsForOrderResult.php
│   │       │   ├── GenerateGiftCard/
│   │       │   │   ├── GenerateGiftCardAction.php
│   │       │   │   └── GenerateGiftCardResult.php
│   │       │   └── ProcessReferral/
│   │       │       ├── ProcessReferralAction.php
│   │       │       └── ProcessReferralInput.php
│   │       ├── Controllers/
│   │       │   └── LoyaltyController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000041_create_loyalty_tables.php
│   │       ├── Listeners/
│   │       │   └── OrderPaidLoyaltyRewardListener.php
│   │       ├── Models/
│   │       │   ├── LoyaltyAccount.php
│   │       │   ├── LoyaltyTransaction.php
│   │       │   └── GiftCard.php
│   │       ├── Repositories/
│   │       │   ├── LoyaltyAccountRepository.php
│   │       │   └── GiftCardRepository.php
│   │       ├── Services/
│   │       │   ├── LoyaltyEngineService.php
│   │       │   └── AILoyaltyProactiveEngine.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Loyalty Module`)
- Enums: `LoyaltyTier`, `LoyaltyTransactionType`, `GiftCardStatus`.
- Migración `2026_08_06_000041_create_loyalty_tables.php`.
- Modelos Eloquent: `LoyaltyAccount`, `LoyaltyTransaction`, `GiftCard`.

### Paso 2: Repositorios & Servicios de Fidelización (`Services/`)
- `LoyaltyAccountRepository` & `GiftCardRepository`.
- `LoyaltyEngineService`: Acumulación, canje, cashback y promoción de nivel VIP.
- `AILoyaltyProactiveEngine`: Fidelización proactiva de clientes inactivos con cupones dinámicos.

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`EarnPointsForOrderAction`**: Ocurre al pagarse un pedido, otorgando puntos y cashback.
- **`RedeemPointsForOrderAction`**: Canjear saldo o puntos en un pedido.
- **`GenerateGiftCardAction`**: Emisión de tarjetas de regalo virtuales.
- **`ProcessReferralAction`**: Bonificación por código de referido.

### Paso 4: Listener & EventBus
- `OrderPaidLoyaltyRewardListener`: Escucha `PaymentApproved` y premia al cliente.

### Paso 5: Controladores, Rutas & RBAC
- `LoyaltyController`:
  - `GET /api/v1/loyalty/account/{customerUuid}` (Consulta de saldo de puntos, cashback y nivel).
  - `POST /api/v1/loyalty/redeem` (Canjear puntos o cashback en pedido).
  - `POST /api/v1/loyalty/gift-cards` (Generar tarjeta de regalo).
  - `POST /api/v1/loyalty/referral` (Procesar bonificación por referido).
- Protegidos mediante `RequirePermission` middleware (`crm:view`, `crm:edit`).

### Paso 6: Suite de Pruebas Automatizadas
- `LoyaltyPointsAccumulationTest`: Acumulación de puntos y cashback tras compra.
- `LoyaltyRedeemTest`: Canje de puntos en el total del pedido.
- `GiftCardAndReferralTest`: Validación de tarjetas de regalo y referidos.
