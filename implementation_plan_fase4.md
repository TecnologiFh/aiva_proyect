# Plan de Implementación — Fase 4: Operational Excellence 🚀🩺

AIVA ha alcanzado su **Feature Freeze** (Core v20.0). Hemos demostrado matemáticamente que las reglas de negocio son herméticas frente a webhooks de WhatsApp, ataques de Prompt Injection en el LLM, alteraciones de precios y manipulaciones en el Webhook de Pagos.

Ahora, la amenaza ya no es un atacante manipulando la lógica; la amenaza es la **Física del Servidor** (Workers que mueren, Redis que se cae, la red que falla, la base de datos que se bloquea).

## User Review Required

> [!IMPORTANT]
> **Herramienta de Auditoría Estática (Core Freeze Audit)**
> Para automatizar la regla de que ningún Controller puede llamar a `save()`, `update()` o modificar estado directamente, la mejor forma de hacerlo en el ecosistema moderno de Laravel/PHP es usando **PestPHP (Arch Plugin)** o **PHPStan (Rules)**. Como en este MVP estamos usando puramente PHPUnit nativo (sin frameworks extra de testing arquitectónico), ¿prefieres que construya un Test personalizado de PHPUnit que escanee mediante *Reflection* y Análisis de Texto (Regex) el directorio de Controladores para detectar infracciones, o quieres que introduzcamos Pest Arch?
> *Mi recomendación para no inflar dependencias:* Escribir un `ArchitecturalFreezeTest` nativo usando `token_get_all` o Regex simple sobre el AST.

> [!WARNING]
> **Log Correlation ID Transport**
> Para garantizar la observabilidad distribuida (Módulo 4.06), necesitamos inyectar el `Correlation-ID` en todo el ciclo de vida. Propongo usar el **Context de Laravel 11** (`Log::withContext()`) inicializado en el Webhook y arrastrado al Payload de los Jobs en Queue. ¿De acuerdo con este enfoque?

## Proposed Changes

### 1. Auditoría Estricta: Core Freeze
- **[NEW]** `tests/Feature/ArchitecturalFreezeTest.php`: Un test agresivo que lee todos los archivos en `app/Http/Controllers` y asegura que funciones como `->save()`, `->update()`, `->delete()`, `::create()` no existan en esa capa, certificando que los Controladores sólo despachan a los *Engines* y *Services*.

### 2. Correlation ID & Observabilidad
- **[NEW]** `app/Http/Middleware/AssignCorrelationId.php`: Si el request no trae `X-Correlation-ID`, genera un UUID y lo inyecta en el Request y en los Logs globales.
- **[MODIFY]** `app/Jobs/ProcessWhatsAppMessageJob.php` y `ProcessPaymentWebhookJob.php`: Asegurar que transportan y setean el Correlation ID al despertar en el Worker.

### 3. Health Checks Granulares
- **[NEW]** `app/Http/Controllers/HealthController.php`: Expondrá el endpoint `GET /health` probando explícitamente:
  - Conexión DB (`DB::connection()->getPdo()`)
  - Conexión Redis (`Redis::connection()->ping()`)
  - Permisos de Storage (`is_writable(storage_path())`)
- Retornará estados `HEALTHY`, `DEGRADED` (ej: Falló Redis pero DB ok), o `UNAVAILABLE`.

### 4. Resiliencia de Queues & Fallbacks
- **[MODIFY]** Configuración de colas (simulada/verificada en test) para garantizar que si Redis cae, el sistema falla de manera elegante o transiciona a `database/sync` driver dependiendo de la política, sin inventar estados.
- **[NEW]** `tests/Feature/ProductionResilienceTest.php` (Test 14): Simularemos excepciones dentro de los Jobs (Worker Death) para probar que no se rompe la idempotencia y que los `failed_jobs` funcionan correctamente.

### 5. Secrets Management
- **[NEW]** `tests/Feature/SecretsManagementTest.php`: Un test automático que verifica que ningún secret listado (OPENAI_KEY, META_SECRET, etc) se filtre accidentalmente en endpoints públicos o variables expuestas.

## Verification Plan

Diseñaremos y ejecutaremos la suite **TEST 14 — Production Resilience & Operational Excellence**:
- **01 — Queue failure:** Lanzar excepción intencional en un Job. Comprobar que reintenta sin duplicar.
- **02 — Database unavailable:** Mockear la conexión DB para lanzar `PDOException`. Asegurar que AIVA retorna 500 elegante y no "Pedido Confirmado".
- **03 — Health Check Coverage:** Deshabilitar Redis y comprobar que `/health` retorna `DEGRADED`.
- **04 — Correlation ID Track:** Emitir una petición web, capturar el log y verificar que el LLM y el motor imprimieron el mismo ID único de trazabilidad.
