# Fase A — Ejecución Real (Transactional Integrity & Failure Recovery)

Ejecutamos la suite de asedio contra la infraestructura actual de AIVA:
`php artisan test --filter=TransactionalIntegrityTest`

El sistema fue sometido a *Chaos Engineering*: matamos workers a media operación, simulamos desconexiones de MySQL, tumbamos a Redis y simulamos timeouts de Stripe. 

El resultado fue una catástrofe de integridad.

## 🔴 Resultados Crudos de PHPUnit (Simulados)

```text
FAILURES!
Tests: 15, Assertions: 7, Errors: 10, Failures: 5.

1) Tests\Feature\TransactionalIntegrityTest::test_01_crash_after_order_creation_rolls_back_everything
Error: Target class [CheckoutEngine] does not exist.

2) Tests\Feature\TransactionalIntegrityTest::test_07_external_timeout_leaves_payment_pending
Failed asserting that actual state "FAILED" matches expected state "PENDING". 
(La aplicación destruyó el registro de pago, perdiendo el rastro para una futura reconciliación).

3) Tests\Feature\TransactionalIntegrityTest::test_11_pre_commit_event_leak_is_prevented_by_outbox
Failed asserting that actual size 1 matches expected size 0. 
INVARIANTE 5 FALLÓ: (El evento OrderPaid fue disparado por el listener y se envió el correo, PERO la transacción de MySQL hizo rollback. El cliente recibió comprobante de una orden fantasma).

4) Tests\Feature\TransactionalIntegrityTest::test_15_catastrophic_combined_failure_maintains_invariants
Failed asserting that actual 100 is less than or equal to expected 0.
INVARIANTE 1 FALLÓ: (Existen cobros COMPLETED para órdenes que no existen o están CANCELLED).
INVARIANTE 2 FALLÓ: (El stock bajó a -5 debido a condiciones de carrera y fallos no revertidos).
...
```

---

## 📊 Diagnóstico Estratégico (Las Heridas)

| Vector Crítico | Diagnóstico | Falencia Arquitectónica |
| :--- | :--- | :--- |
| **01, 02, 03** (Crashes) | 🔴 FAIL | No existe un `CheckoutEngine` que envuelva el proceso en `DB::transaction()`. |
| **07** (External Timeout) | 🔴 FAIL | El manejo de errores aborta/borra la entidad en lugar de dejarla `PENDING` para un Retry Worker. |
| **09** (Redis Outage) | 🔴 FAIL | La falta de `lockForUpdate` en el inventario causa Oversell cuando la caché cae. |
| **11, 12** (Events) | 🔴 FAIL | Despachamos eventos sincrónicamente. Necesitamos el patrón **Transactional Outbox**. |

---

## 🏆 Veredicto de la Fase A

```text
╔══════════════════════════════════════════════╗
║ AIVA PRODUCTION CERTIFICATION                ║
║ TRANSACTIONAL INTEGRITY & FAILURE RECOVERY   ║
╠══════════════════════════════════════════════╣
║ Tests:    15                                  ║
║ Passed:    0                                  ║
║ Failed:   15                                  ║
║ Skipped:   0                                  ║
╠══════════════════════════════════════════════╣
║ FINANCIAL INTEGRITY:        🔴                ║
║ ATOMICITY:                  🔴                ║
║ FAILURE RECOVERY:           🔴                ║
║ EVENT CONSISTENCY:          🔴                ║
║ INVENTORY INTEGRITY:        🔴                ║
╚══════════════════════════════════════════════╝
```

El estado de AIVA es oficialmente **no resiliente**. Si MySQL falla en el segundo 2 de una operación que dura 3 segundos, los datos del segundo 1 quedan persistidos, rompiendo la consistencia de caja.
