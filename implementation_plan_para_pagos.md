# Master Implementation Plan — FASE P2.11: Payment Evidence Intelligence & Reconciliation Engine (Contract v1.0)

## Resumen Ejecutivo
El objetivo de la **Fase P2.11** es implementar el **Payment Truth Layer** de AIVA Commerce v21.1, un motor financiero desacoplado y fail-closed que garantiza que la IA actúe únicamente como extractor de información y que la autorización de pago (`PAID`) sea una decisión exclusiva del motor determinístico.

```
╔══════════════════════════════════════════════════════════════╗
║          AIVA COMMERCE v21.1 — PHASE P2.11 STATUS             ║
╠══════════════════════════════════════════════════════════════╣
║  Phase                         Payment Truth Layer         ║
║  Architecture Specification    🟢 FROZEN (Contract v1.0)   ║
║  Core v21.1 Baseline           🔒 SEALED (84/84 PASS)     ║
║  P2.9.2 Governance             🔒 SEALED (30/30 PASS)     ║
║  P2.9.3 Tenant UX              🔒 SEALED (16/16 PASS)     ║
║  P2.10 Production              🔒 SEALED (23/23 PASS)     ║
║  P2.11 Implementation          🟡 NEXT STEP               ║
║  PAY1–PAY33 Certification      🟡 PENDING EXECUTION        ║
║  Gold Dataset Benchmark        🟡 PENDING EXECUTION        ║
╚══════════════════════════════════════════════════════════════╝
```

---

## User Review Required

> [!IMPORTANT]
> **Principio de las Tres Verdades (Payment Truth Contract)**:
> - $\text{Evidence Truth (IA/OCR)} \neq \text{Validation Truth (Reglas V1-V10)} \neq \text{Payment Truth (Autorización Financiera)}$.
> - **La IA NUNCA ejecuta `$order->markAsPaid()` de forma autónoma.**
> - **Solo `PaymentAuthorizationService` puede mutar un pedido a `PAID`.** Intentos directos desde Agents, Webhooks, Controllers o Queue Jobs son bloqueados por el dominio y probados en los Gates **PAY33-A a PAY33-F**.

> [!IMPORTANT]
> **Reconciliación Configurable (Exact / Partial / Overpayment)**:
> - `EXACT_PAYMENT`: Coincidencia al 100% en soles (`DECIMAL 12,2`).
> - `PARTIAL_PAYMENT`: Registra el abono parcial, calcula `remaining_balance` y mantiene el pedido en `PENDING_PAYMENT`.
> - `OVERPAYMENT`: Respeta la política del tenant (`tenant_payment_settings`): `REJECT`, `MANUAL_REVIEW`, `ACCEPT_AND_REFUND`, `ACCEPT_AS_CREDIT`.

> [!NOTE]
> **KPI Principal & Safety Boundary**:
> - **0% False Positive Auto-Authorization Rate ($FPR = 0\%$)** sobre el Gold Dataset v1.0. Ante duda, el sistema deriva a `MANUAL_REVIEW` con Reason Codes oficiales.

---

## Proposed Changes (Fase P2.11 por Sub-Hitos)

```
P2.11.1: Domain Models + DB Migrations + 8-Stage State Machine
P2.11.2: Evidence Intake + SHA-256 & pHash Fingerprinting
P2.11.3: Yape Extractor Adapter + Canonical Normalizer
P2.11.4: Deterministic V1–V10 Validation Engine
P2.11.5: Fraud Detection + Replay Protection Engine
P2.11.6: Risk & Authorization Policy Engine
P2.11.7: Human Review Inbox (Reason Codes Audit)
P2.11.8: WhatsApp E2E Integration
P2.11.9: Gold Dataset Benchmark v1.0
P2.11.10: Certificación Máster PAY1–PAY33 (0% FPR)
```

---

### Componente 1: Domain Models & Canonical Payment Schema (`app/Modules/Payments/Domain`)

#### [NEW] [CanonicalPaymentEvidence.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Domain/ValueObjects/CanonicalPaymentEvidence.php)
- Encapsula payload normalizado, tipos `DECIMAL(12,2)`, niveles de confianza por campo, coordenadas bounding box y hashes (`SHA-256` + `pHash`).

#### [NEW] [PaymentEvidenceProviderInterface.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Domain/Contracts/PaymentEvidenceProviderInterface.php)
- Contrato estándar para clasificadores y extractores de proveedores (Yape, Plin, BCP, BBVA, Interbank, Scotiabank).

#### [NEW] [PaymentSourceVerifierInterface.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Domain/Contracts/PaymentSourceVerifierInterface.php)
- Interfaz para futura integración con extractos bancarios o APIs transaccionales en tiempo real (Nivel 3 de Certeza).

---

### Componente 2: Provider Adapters (`app/Modules/Payments/Infrastructure/Providers`)

#### [NEW] [YapeEvidenceProvider.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Infrastructure/Providers/Yape/YapeEvidenceProvider.php)
- Adaptador especializado (Classifier, Extractor, Normalizer) para comprobantes de Yape BCP.

---

### Componente 3: Deterministic Rule & Fraud Engine (`app/Modules/Payments/Application/Services`)

#### [NEW] [DeterministicValidationEngine.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Application/Services/DeterministicValidationEngine.php)
- Ejecuta las 10 Reglas V (`V1_AMOUNT`, `V2_CURRENCY`, `V3_DESTINATION`, `V4_TIME_WINDOW`, `V5_OPERATION_ID`, `V6_DUPLICATE`, `V7_TENANT`, `V8_ORDER_BINDING`, `V9_EXPIRATION`, `V10_OVERPAYMENT`).

#### [NEW] [PaymentAuthorizationService.php](file:///C:/xampp/htdocs/AIVA/app/Modules/Payments/Application/Services/PaymentAuthorizationService.php)
- Único punto de autorización transaccional en MySQL (`SELECT FOR UPDATE`) para transicionar el pedido a `PAID`.

---

### Componente 4: Database Migrations & Persistence (`database/migrations`)

#### [NEW] [2026_08_26_000100_create_payment_evidences_table.php](file:///C:/xampp/htdocs/AIVA/database/migrations/2026_08_26_000100_create_payment_evidences_table.php)
- Tablas `payment_evidences`, `payment_evidence_files`, `payment_extractions`, `payment_validations`, `payment_decisions` (Insert-Only con Reason Codes), `payment_allocations` y `payment_audit_events`.

---

## Verification Plan

### Automated Tests
- Ejecución del Security Boundary Test **PAY33-A a PAY33-F** y los Gates PAY1 a PAY32 vía endpoint:
  ```bash
  curl -X GET http://localhost/AIVA/public/api/v1/payment-evidence/run-suite
  ```
- Evaluaciones con Gold Dataset Benchmark v1.0 comprobando 0% de Falsos Positivos para autorizaciones automáticas.

### Manual Verification
- Pruebas E2E desde WhatsApp con comprobantes de Yape reales.
- Verificación del Panel de Revisión Humana de Razones (`AMOUNT_MISMATCH`, `OVERPAYMENT`, `PARTIAL_PAYMENT`, `LOW_CONFIDENCE`).
