# Plan de Implementación: TEST 2 — Idempotency & Concurrency 🔄

Con el Aislamiento Multi-Tenant (Test 1) consolidado en código, pasamos a la segunda prueba crítica del **AIVA Production Certification Suite**: Garantizar que el sistema es matemáticamente consistente incluso bajo condiciones de alto estrés concurrente.

## 🎯 Objetivo de la Prueba

Demostrar irrevocablemente que AIVA es inmune a **condiciones de carrera (Race Conditions)** y **Webhooks duplicados**, asegurando que 1 evento de origen (Ej. WhatsApp o SUNAT) produzca exactamente **1 solo resultado** en la base de datos (Ej. 1 Pedido, 1 Cobro), sin importar cuántas veces se reintente o con cuánta concurrencia llegue.

## 🛠️ Vectores de Ataque a Programar

Crearemos el archivo `tests/Feature/IdempotencyConcurrencyTest.php` utilizando PHPUnit, que ejecutará los siguientes vectores de estrés:

### 1. Vector: WhatsApp Webhook Avalanche (Doble Pedido)
- **Escenario**: La API de Meta reintenta 5 veces el mismo webhook porque el servidor tardó en responder.
- **Acción**: Disparar 5 hilos simulados (o bucle sincrónico) inyectando el mismo payload de WhatsApp con el mismo `MessageId` (o `Idempotency-Key`).
- **Aserción (PASS)**: La base de datos registra exactamente `1 Order`. Los otros 4 webhooks deben retornar `200 OK` silenciados, o arrojar `DuplicateWebhookException`.

### 2. Vector: Race Condition en Pagos (Doble Cobro)
- **Escenario**: Un cliente hace clic en "Pagar" dos veces súper rápido en la interfaz, o llegan dos webhooks de Niubiz al mismo milisegundo.
- **Acción**: Intentar cambiar el estado de la misma `Order` a `PAID` dos veces simultáneamente.
- **Aserción (PASS)**: Solo el primer intento debe realizar la transacción (`DB::transaction` / `Redis::lock`). El segundo debe retornar un estado indicando "Ya procesado", evitando registrar dos veces el ingreso en el `Cost Center`.

### 3. Vector: Loyalty Points Race Condition (Gasto Doble)
- **Escenario**: Un cliente con 100 puntos intenta canjear un premio de 100 puntos desde dos dispositivos al mismo tiempo.
- **Acción**: Iniciar 2 transacciones de base de datos (`lockForUpdate()`) simultáneas que descuentan los 100 puntos.
- **Aserción (PASS)**: La primera transacción tiene éxito (saldo = 0). La segunda falla por fondos insuficientes. (Validación de bloqueo de filas ACID en MySQL).

### 4. Vector: AI Guardrail Command Idempotency
- **Escenario**: La IA emite el comando de negocio `ExecuteDiscount: 15%` dos veces por error cognitivo en el mismo turno de conversación.
- **Acción**: El `DecisionEngine` procesa dos comandos idénticos con el mismo `ConversationTurnId`.
- **Aserción (PASS)**: El motor de políticas aplica el descuento a la orden solo 1 vez. El monto de la orden debe reflejar un 15% menos, no un 30%.

### 5. Vector: SUNAT PSE Fallback Retry
- **Escenario**: AIVA envía una boleta a SUNAT, hay timeout, AIVA reintenta el envío.
- **Acción**: El Job de facturación electrónica se despacha 2 veces por timeout de red simulado.
- **Aserción (PASS)**: El PSE o AIVA detectan el mismo `Order_ID` y evitan generar dos XML firmados distintos, retornando el hash del XML original generado en el primer intento.

---

## 🏗️ Implementación Técnica Propuesta

1. **Setup del Test**: 
   - DB limpia (`RefreshDatabase`).
   - Mocking ligero de concurrencia o loops de aserción.
2. **Métodos de Test**:
   - `test_whatsapp_webhook_avalanche_creates_only_one_order()`
   - `test_payment_approval_race_condition()`
   - `test_loyalty_redemption_race_condition_locked()`
   - `test_ai_decision_engine_idempotency()`
   - `test_sunat_invoice_retry_prevents_duplicate_xml()`

---

## ⚠️ User Review Required

> [!IMPORTANT]
> ¿Estás de acuerdo con estos 5 vectores de concurrencia e idempotencia? Al igual que con el Test 1, si me das luz verde, escribiré el código fuente real (`IdempotencyConcurrencyTest.php`) asegurándome de usar mecanismos como `$this->withoutExceptionHandling()`, `DB::transaction()`, y aserciones de filas (`$this->assertDatabaseCount('orders', 1)`) para blindar matemáticamente las transacciones de AIVA.
