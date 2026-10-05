# Especificación Técnica Definitiva Congelada: AIVA Commerce PR14–PR23

> **ESTADO DEL DOCUMENTO:** 🧊 ESPECIFICACIÓN OFICIALMENTE CONGELADA Y AUDITADA  
> **VALORACIÓN DE ARQUITECTURA:** 🏆 10/10 — LISTA PARA IMPLEMENTACIÓN DIRECTA  
> **ÁMBITO:** Extensión Vertical de AIVA Commerce (sin alterar el Core Contract v21.0 Freeze)  
> **MÁXIMA ARCHITECTURAL LEY:** *"El LLM propone y explica. Los Tools consultan. El Core decide. La Base de Datos garantiza. Los Eventos notifican."*

---

## 🏛️ Matriz de Módulos & Capas del Sistema

```
                     ┌──────────────────────────────────────────────┐
                     │                  AIVA LLM                    │
                     │ Conversa · Interpreta · Propone · Explica    │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │                   TOOLS                      │
                     │ Product · Stock · Payment · Order · Handover │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │                 CORE v21.0                   │
                     │                  🔒 FROZEN                   │
                     │       Reglas · Autorización · Decisiones     │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │                DATABASE                      │
                     │ Constraints · Transactions · Locks · Unique  │
                     └──────────────────────┬───────────────────────┘
                                            │
                                            ▼
                     ┌──────────────────────────────────────────────┐
                     │                  EVENTS                      │
                     │ Realtime · Inbox · Alerts · Telemetry        │
                     └──────────────────────────────────────────────┘
```

---

## 🔒 Los 7 Ajustes de Endurecimiento Técnico Congelados

### 1. Invariante Matemática & Separación de Estados de Reserva (PR14 – PR15)
- **Invariante Matemática del Dominio (Verificada Post-Mutación):**
  $$\text{initial\_stock} = \text{available\_stock} + \text{reserved\_stock} + \text{sold\_stock}$$
  *Ejecución obligatoria:*
  ```php
  assert($initial_stock === ($available_stock + $reserved_stock + $sold_stock), 'Invariante de Stock Rota');
  ```
- **Separación de Estados:**
  - **Estado Agregado de Stock Diario:** `ACTIVE`, `SOLD_OUT`.
  - **Estado Individual de Reserva:** `PENDING`, `RESERVED`, `CONFIRMED`, `EXPIRED`, `CANCELLED`, `RELEASED`.

---

### 2. Tratamiento Especial de `operation_number` Ausente (PR20 – PR23)
- La ausencia de un número de operación extraído vía OCR/Visión **jamás** se interpreta como evidencia positiva de duplicidad o reutilización.
- **Regla:** Si `operation_number` es NULO ➔ Clasificar como `MANUAL_REVIEW` para inspección visual del operador. Jamás marcar `PAYMENT_PROOF_REUSED` automáticamente.

---

### 3. Handover con UUID + `attention_code` + Métricas SLA (PR18)
- **Identificación:**
  - `id`: CHAR(36) ULID / UUID técnico global.
  - `attention_code`: `ATT-YYYYMMDD-XXXX` (Visibilidad humana por Tenant).
  - Restricción BD: `UNIQUE(tenant_id, attention_code)`.
- **Telemetría SLA:**
  $$\text{MTTA (Mean Time To Acknowledge)} = \text{acknowledged\_at} - \text{created\_at}$$
  $$\text{MTTR (Mean Time To Resolve)} = \text{resolved\_at} - \text{created\_at}$$

---

### 4. Doble Nivel de Verificación de Vouchers & Idempotencia (PR19 – PR23)
- **Nivel 1 (Huella de Archivo Binario):** `voucher_hash` (`sha256`), `UNIQUE(tenant_id, voucher_hash)`.
- **Nivel 2 (Firma Lógica de Operación):**
  $$\text{PAYMENT\_OPERATION\_FINGERPRINT} = \text{sha256}(\text{tenant\_id} + \text{provider} + \text{operation\_number} + \text{amount} + \text{destination\_phone} + \text{date})$$
- **Regla `MATCHED \neq VERIFIED`:** `MATCHED` certifica coincidencia técnica (monto, titular, teléfono), manteniendo `payment_status = PENDING` hasta confirmación autorizada (`VERIFIED`).
- **Invalidación:** Al cancelar un pedido, el comprobante asociado se desvincula para impedir su reutilización.

---

### 5. Reglas Híbridas de Producto (Core Authoritative) (PR16 – PR17)
- **Atributos Deterministas Core (AUTHORITATIVE):** `requires_advance`, `advance_hours`, `requires_deposit`, `deposit_percentage`.
- **Instrucciones Contextuales IA (NON_AUTHORITATIVE):** `ai_instructions`.
- Si `ai_instructions` entra en contradicción con una regla Core, la regla Core del sistema prevalece deterministamente en `GetProductInfoTool`.

---

### 6. Barrera de Seguridad Multi-Tenant & Fail Closed (API / Queues)
- **Principio Inviolable:** **NO TENANT CONTEXT ➔ NO DATABASE ACCESS (FAIL CLOSED)**.
- **Fail Closed en Jobs:** Cualquier Job asíncrono (`AnalyzePaymentVoucherJob`, `StockReservationExpiredJob`, `SendHandoverNotificationJob`) que reciba `tenant_id = null` cancela inmediatamente su ejecución (`NO EXECUTE`), emite alerta y registra el evento en log.
- **Aislamiento de Recursos:** Si `TenantContext` = A intenta acceder a un recurso cuyo `tenant_id` = B ➔ Retornar `ACCESS DENIED` / `404 Not Found`.

---

### 7. Formalización de Servicio 24/7
- Aislamiento explicativo:
  $$\text{AI\_SERVICE\_AVAILABILITY (24/7)} \neq \text{BUSINESS\_HOURS} \neq \text{PRODUCT\_AVAILABILITY} \neq \text{FULFILLMENT\_AVAILABILITY}$$
- AIVA responde y procesa las 24 horas del día programando despachos según las condiciones de preparación del producto.

---

## 🛠️ Esquema de Base de Datos Enriquecido

```sql
-- TABLA DE STOCK DIARIO
CREATE TABLE daily_stocks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    date DATE NOT NULL,
    initial_stock INT UNSIGNED DEFAULT 0,
    available_stock INT UNSIGNED DEFAULT 0,
    reserved_stock INT UNSIGNED DEFAULT 0,
    sold_stock INT UNSIGNED DEFAULT 0,
    status VARCHAR(30) DEFAULT 'ACTIVE', -- ACTIVE, SOLD_OUT
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_tenant_product_date (tenant_id, product_id, date),
    INDEX idx_tenant_date (tenant_id, date, status)
);

-- TABLA DE ATENCIONES DE HANDOVER
CREATE TABLE handover_attentions (
    id CHAR(36) PRIMARY KEY,
    attention_code VARCHAR(50) NOT NULL,
    tenant_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    status VARCHAR(30) DEFAULT 'CREATED', -- CREATED, ACKNOWLEDGED, RESOLVED
    created_at TIMESTAMP NULL,
    acknowledged_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    UNIQUE KEY uk_tenant_attention_code (tenant_id, attention_code),
    INDEX idx_tenant_status (tenant_id, status)
);

-- TABLA DE COMPROBANTES DE PAGO
CREATE TABLE payment_vouchers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    order_id BIGINT NOT NULL,
    voucher_hash VARCHAR(64) NOT NULL,
    operation_fingerprint VARCHAR(64) NOT NULL,
    extracted_amount DECIMAL(10,2) NULL,
    extracted_date TIMESTAMP NULL,
    extracted_operation_number VARCHAR(100) NULL,
    extracted_destination_phone VARCHAR(50) NULL,
    extracted_destination_holder VARCHAR(150) NULL,
    status VARCHAR(30) DEFAULT 'RECEIVED', -- RECEIVED, ANALYZING, MATCHED, MISMATCH, REUSED, MANUAL_REVIEW, VERIFIED, REJECTED
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uk_tenant_voucher_hash (tenant_id, voucher_hash),
    INDEX idx_tenant_fingerprint (tenant_id, operation_fingerprint)
);
```

---

## 🧪 Suite de Pruebas Automatizadas de Seguridad & Concurrencia

| Código | Módulo | Tipo de Prueba | Criterio de Éxito |
| :--- | :--- | :--- | :--- |
| **PR14-A** | Stock | Invariante Matemática | Assert `initial == available + reserved + sold` tras cada operación. |
| **PR14-F** | Stock | Concurrencia | 100 peticiones simultáneas con `lockForUpdate()`. Cero overselling. |
| **PR16-B** | Reglas | Authoritative Core | Core bloquea entregas en < 24h ignorando `ai_instructions` contradictorias. |
| **PR18-A** | Handover | Alerta & Identificación | Disparo de `HUMAN_REQUIRED` genera `ATT-20260820-0001` y emite sonido "AIVA Attention". |
| **PR18-E** | Handover | Telemetría SLA | Medición exacta de MTTA tras botón `ACKNOWLEDGED`. |
| **PR19-A** | Voucher | Matching Técnico | `PAYMENT_PROOF_STATUS = MATCHED` manteniendo `payment_status = PENDING`. |
| **PR19-E** | Voucher | Detección de Reuso | Voucher con mismo `operation_fingerprint` ➔ `PAYMENT_PROOF_REUSED` ➔ `MANUAL_REVIEW`. |
| **PR19-G** | Seguridad | Aislamiento Tenant | Tenant A solicita recurso de Tenant B ➔ `ACCESS DENIED / 404`. |
| **PR19-H** | Seguridad | Job sin Tenant | Job disparado con `tenant_id = null` ➔ `FAIL_CLOSED` (Cancelado inmediatamente). |
