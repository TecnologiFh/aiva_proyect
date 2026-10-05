# Plan de Implementación: TEST 8 — Contract & Integration 🌐⚔️

Tienes absoluta razón. Los 7 muros han resistido los asedios de manera individual, pero el verdadero desafío de una arquitectura distribuida (y la causa del 90% de los incidentes en producción) ocurre en las **intersecciones**.

El Test 8 será el **Examen Final (The Grand Unified Certification)**. Probaremos que un flujo físico real (desde el teléfono del cliente hasta el disco duro de la base de datos y la red del procesador de pagos) sobrevive al caos total sin violar ninguna de las 35 invariantes de los tests anteriores.

## 🌉 El Flujo a Asediar
`WhatsApp ➔ Webhook Sec ➔ Idempotency ➔ Tenant Res ➔ Queue ➔ AI Guardian ➔ Tools ➔ CheckoutEngine ➔ PaymentStateMachine ➔ Outbox ➔ Observability`

## 💥 Los 15 Vectores de Integración (La Tormenta Perfecta)

Construiremos `tests/Feature/EndToEndIntegrationTest.php` para disparar estos 15 escenarios de catástrofe simultánea:

1. **The Happy Path (E2E):** `Webhook` → `Job` → `AI` → `CreateOrderTool` → `CheckoutEngine` = Orden perfecta.
2. **Duplicate Webhook vs AI:** Meta envía el mismo Webhook 3 veces mientras la IA intenta invocar el `Checkout`. (Debe garantizar 1 sola orden y 3 `200 OK` a Meta).
3. **Cross-Tenant Customer Collision:** Dos Tenants distintos reciben un mensaje del *mismo número de teléfono* del cliente al mismo tiempo. (El `WabaTenantResolver` y el AI Guardian deben mantener los carritos y el contexto estrictamente separados).
4. **Injection to Transact:** Un Prompt Injection intenta invencer a la IA de invocar el `CheckoutEngine` con un precio de `$0`. (La barrera de Determinismo de B3/Test 7 debe rechazarlo en medio de la charla).
5. **WhatsApp Double-Spend:** El cliente escribe "¡Págualo!" dos veces en milisegundos. Dos workers arrancan en paralelo intentando invocar la `PaymentStateMachine`.
6. **Payment Provider Timeout in-flight:** El cliente confirma la compra vía WhatsApp, la IA invoca el cobro, pero Stripe/Niubiz hace timeout (simulado). La conversación de WhatsApp debe reflejar el estado "Pendiente/Error" sin crashear.
7. **Worker Death post-Checkout:** El Checkout es exitoso en DB, pero el Worker muere (OOM / Kill) antes de responder a OpenAI o generar el Outbox Event final.
8. **Redis Outage during Sales:** Redis (usado para Rate Limiting y Locks) se cae a la mitad del procesamiento de una venta iniciada por WhatsApp.
9. **Outbox Latency (Asynchronous Lag):** El evento `OrderPaid` se guarda en el Outbox, pero el cron/worker asíncrono está retrasado 5 minutos.
10. **Tenant Switch Mid-Flight:** Simularemos que una cabecera o WABA ID cambia maliciosamente a la mitad del pipeline del Job asíncrono.
11. **Race Condition (Cancel vs Pay):** El administrador cancela el pedido en el panel web (Admin) *exactamente en el mismo milisegundo* en que el LLM invoca el pago vía Tool.
12. **Race Condition (Stock vs Offer):** El LLM le dice al cliente "Sí, me queda 1 Zapatilla". El cliente responde "Cómprala", pero otro cliente (o tenant) consumió el stock milisegundos antes del `CheckoutEngine`.
13. **Meta Retry on Crash:** El Worker falla (ej. DB caída transitoria). Meta no recibe el 200 rápido (por fallo en B3), Meta reintenta 5 minutos después cuando el sistema ya volvió.
14. **Disaster Recovery during Transaction:** Simularemos una venta completada. Inmediatamente hacemos Drop de la BD, Restore del Backup, y verificamos que el Dashboard del Tenant sigue viendo la orden con sus saldos exactos.
15. **The Catastrophe:** Todos los fallos a la vez. Webhook duplicado + IA inyectada + Concurrencia de pago + Worker inestable.

---

## ⚖️ El Criterio del Examen Final

No verificaremos solo un `assertStatus(200)`. El Test 8 será un análisis forense de la Base de Datos post-ataque.

Al finalizar la *Catastrophe*, el Test comprobará físicamente:
1. `orders` table (Saldos correctos, 0 duplicados).
2. `inventory` table (Stock real, sin negativos).
3. `payments` table (Estado final determinista).
4. `webhook_idempotency` table (Replays atrapados).
5. `audit_logs` table (Toda la traza de la IA y el Checkout almacenada con Tenant/User).

---

## ⚠️ User Review Required

> [!IMPORTANT]
> **Plan de Ejecución del Test 8: El Examen Final de Integración**
> 
> Tienes toda la razón en exigir esta prueba. Es la única forma de garantizar que AIVA v20 no se desmorone por efectos en cascada en producción.
> 
> **¿Apruebas estos 15 vectores de integración masiva?**
> 
> Si me das luz verde, implementaré la **Fase A (Reality Check)** para el Test 8 creando el esqueleto del `EndToEndIntegrationTest.php` y reportando cómo reacciona el sistema ante las fallas combinadas.
