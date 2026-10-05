# Especificación Maestra Definitiva Congelada: FASE C — AIVA Production Readiness & Industrialización SaaS (AIVA Commerce v21.1)

> **ESTADO DEL DOCUMENTO:** 🧊 ESPECIFICACIÓN MAESTRA DE PRODUCCIÓN CERTIFICADA 10/10  
> **ÁMBITO:** Industrialización, Infraestructura, Colas, Seguridad, Observabilidad y Piloto Comercial  
> **MÁXIMA LEY DE ARQUITECTURA:** *"El LLM interpreta y explica. Los Tools consultan. El Core decide. La Base de Datos garantiza. La Infraestructura sostiene 24/7. La Telemetría supervisa."*

---

## 🏛️ Matriz de Capas del Sistema en Producción 24/7

```
                 AIVA COMMERCE v21.1
                         │
                    C0 AUDIT GATE
                         │
        ┌────────────────┴────────────────┐
        │                                 │
   STAGING ENV                       PRODUCTION
 (Exacto a Prod)                      (24 / 7)
        │                                 │
        └──────────────┬──────────────────┘
                       ▼
                 RUNTIME 24/7
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
      Queue          Redis          Scheduler
        │              │
        ▼              ▼ (Fail-Safe Recovery Queue)
     Workers     Emergency Log
        │
        ▼
    AIVA CORE (v21.0 FROZEN)
        │
 ┌──────┼─────────┐
 ▼      ▼         ▼
MySQL  AI       WhatsApp
        │
        ▼
  CONTROL PLANE (/admin)
 ┌──────┼─────────┐
 ▼      ▼         ▼
Tenants Health  Costs (Unit Economics)
```

---

## 📋 Secuencia Real de Ejecución de la FASE C

```
C0  Production Readiness Audit Gate (Auditoría del Repositorio Actual)
 ↓
C2  Entornos e Infraestructura 24/7 (Local ➔ Staging ➔ Production)
 ↓
C3  Runtime Asíncrono 24/7 (Redis + Queues + Workers + Job Safety + Redis Fail-Safe Recovery Queue)
 ↓
C5  Seguridad + Secrets (.env.production) + RPO/RTO + Disaster Recovery + Production Migrations
 ↓
C4  Control Plane (/admin) & Health Matrix (Green / Yellow / Red / Unknown)
 ↓
C6  Production Certification Suite (20 Gates Automatizados + Deployment Rollback)
 ↓
C7  DonutsFs Pilot (Operación Real 24/7 en Vivo)
 ↓
C8  Periodo de Estabilización & Criterios de Salida del Piloto (Exit Criteria)
 ↓
C9  Multi-Vertical Validation (Pastelería ➔ Restaurante ➔ Retail - Zero Core Code Changes)
 ↓
C9.5 Commercial Readiness & Unit Economics (Modelo de Costos SaaS por Tenant)
 ↓
C10 AIVA Commerce v21.1 Public Release
```

---

## 🔒 Especificaciones Técnicas Certificadas por Sub-Fase

### C0 — Production Readiness Audit Gate (Auditoría del Repositorio Actual)
Punto de control inicial antes de aprovisionar infraestructura o Staging. Inspecciona el código real en `C:\xampp\htdocs\AIVA` y genera el informe oficial:
```
C0 AUDIT REPORT
│
├── 🔴 BLOCKERS (Impiden ejecución fuera de XAMPP)
├── 🟠 REQUIRED BEFORE STAGING (Necesarios para desplegar en Staging)
├── 🟡 HARDENING (Mejoras de seguridad y optimización)
└── 🟢 PRODUCTION READY (Componentes homologados para producción)
```
- **Focos de Auditoría:**
  1. Reemplazo de llamadas `env()` en controladores por `config()`.
  2. Rutas relativas de archivos en `storage/app/public`.
  3. Estado e idempotencia de migraciones y seeders.
  4. Formato de logs estructurados con `correlation_id`.
  5. Inmunidad a excepciones no capturadas (Fail Closed).

---

### C2 — Entornos & Paridad de Infraestructura (Local ➔ Staging ➔ Production)
- **Regla de Paridad Inviolable:** Staging debe ser 100% reproducible a partir de la misma configuración de producción (mismo SO, misma versión de PHP, NGINX, MySQL, Redis y Supervisor).
- **Stack Tecnológico Homologado (2026):**
  - **SO:** Ubuntu 24.04 LTS.
  - **Servidor Web:** NGINX 1.26+ con HTTP/2, TLS 1.3 (Certbot / Cloudflare SSL).
  - **Ejecución PHP:** PHP 8.2 / 8.3 FPM (`opcache.enable=1`, `opcache.jit=tracing`, `APP_DEBUG=false`).
  - **Base de Datos:** MySQL 8.0 Community Edition (Optimizado para SSD, conexiones encriptadas).
  - **Caché y Colas:** Redis 7.0 Server con autenticación.
  - **Process Manager:** Supervisor gestionando colas y workers de Laravel Horizon.

---

### C3 — Runtime Asíncrono 24/7, Job Safety & Redis Fail-Safe Recovery Queue
- **Redis Fail-Safe Recovery Queue:**
  Si Redis cae, el Webhook de WhatsApp responde `HTTP 200 OK` de inmediato a Meta y registra el evento entrante en la tabla de recuperación durable `inbound_webhook_emergency_logs` en MySQL:
  ```sql
  CREATE TABLE inbound_webhook_emergency_logs (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      event_id VARCHAR(100) NOT NULL,
      tenant_id BIGINT NOT NULL,
      payload_hash VARCHAR(64) NOT NULL,
      payload LONGTEXT NOT NULL,
      received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      processed_at TIMESTAMP NULL,
      attempts INT DEFAULT 0,
      status VARCHAR(20) DEFAULT 'PENDING', -- PENDING, PROCESSING, PROCESSED, FAILED
      last_error TEXT NULL,
      correlation_id VARCHAR(64) NOT NULL,
      UNIQUE KEY uk_tenant_event (tenant_id, event_id),
      INDEX idx_status_received (status, received_at)
  );
  ```
  Al restablecerse Redis, un worker de recuperación procesa la cola de emergencia y la reinyecta en el flujo normal de AIVA Core.

- **Job Safety Contract & Deduplicación Efectiva:**
  Todos los Jobs asíncronos (`ProcessWhatsAppInboundJob`, `AnalyzePaymentVoucherJob`, `StockReservationExpiredJob`, `InitializeDailyStocksJob`) incluyen deduplicación previa para evitar ejecuciones concurrentes del mismo evento (`EVENT-ABC`):
  ```php
  public int $tries = 3;
  public int $backoff = 5; // segundos
  public int $timeout = 30; // segundos
  public int $uniqueFor = 60; // deduplicación por 60 segundos
  public string $idempotencyKey;
  public string $correlationId;
  ```

---

### C5 — Seguridad, Secret Management, RPO/RTO & Protocolo de Migraciones

- **Secret Management (.env.production):** Cero secretos (API keys, contraseñas, tokens) en Git ni en logs. Inyección segura en servidor mediante almacenamiento cifrado.
- **Definiciones Técnicas de Tolerancia a Desastres:**
  - **RPO (Recovery Point Objective):** $\le 15$ minutos (Máxima pérdida aceptable de datos).
  - **Database Restore Time:** $< 15$ minutos (Tiempo de restauración completa del dump MySQL).
  - **Full Service RTO (Recovery Time Objective):** $\le 60$ minutos (Restablecimiento total del servicio SaaS 24/7).
- **Production Migration Protocol:**
  Protocolo de migración seguro y compatible hacia atrás:
  $$\text{Backup Pre-Migración} \longrightarrow \text{Preflight Check} \longrightarrow \text{Backward-Compatible Migration} \longrightarrow \text{Health Check} \longrightarrow \text{Release}$$

---

### C4 — AIVA Control Plane (`/admin`) & Health Matrix Estructurada
- **Ruta:** `/admin` (Exclusivo para Super Administrador).
- **Aislamiento de Dominio:** El Control Plane invoca Use Cases y Domain Services. **Jamás muta tablas de negocio mediante SQL directo**.
- **Health Matrix Estructurada:**
  Estados: `GREEN` 🟢, `YELLOW` 🟡, `RED` 🔴, `UNKNOWN` ⚪.
  Respuesta JSON completa:
  ```json
  {
    "service": "Redis Queue Worker",
    "status": "GREEN",
    "latency_ms": 2,
    "last_check": "2026-08-21 21:45:00",
    "error_rate": "0.00%",
    "active_workers": 4,
    "version": "21.1-FREEZE",
    "uptime_seconds": 864000
  }
  ```

---

### C6 — Production Certification Suite (20 Gates Automatizados)

| # | Gate de Certificación | Descripción | Criterio de Éxito |
| :--- | :--- | :--- | :--- |
| 1 | Core Contract v21.0 Integrity | Invariabilidad de contratos v21.0 | 🟢 PASS |
| 2 | Multi-Tenant Cross-Isolation | Regla `NO TENANT CONTEXT -> FAIL CLOSED` | 🟢 PASS |
| 3 | Provisioning Pipeline | Aprovisionamiento automatizado de Tenants | 🟢 PASS |
| 4 | Business Health Engine | Evaluación de estado de salud previo a Prod | 🟢 PASS |
| 5 | WhatsApp Webhook Idempotency | Procesamiento de eventos duplicados | 🟢 PASS |
| 6 | AI Guardian & Tool Registry | Ejecución determinista de Tools | 🟢 PASS |
| 7 | Daily Stock Invariant Check | Assert `initial == available + reserved + sold` | 🟢 PASS |
| 8 | Order 4D State Machine | Transiciones válidas en ciclo de orden | 🟢 PASS |
| 9 | Payment Voucher Engine | Firma `PAYMENT_OPERATION_FINGERPRINT` | 🟢 PASS |
| 10 | Handover Protocol & SLA | Medición exacta de MTTA y MTTR | 🟢 PASS |
| 11 | Async Queue Workers | Procesamiento en segundo plano vía Redis | 🟢 PASS |
| 12 | Midnight Stock Scheduler | Ejecución automatizada de colas a las 00:01 | 🟢 PASS |
| 13 | High Concurrency Lock Test | 100 peticiones simultáneas con `FOR UPDATE` | 🟢 PASS |
| 14 | Automated DB Restore Test | Restauración completa de MySQL en $< 15$ min | 🟢 PASS |
| 15 | Security & CSRF Protection | Protección anti-CSRF y sanitizado de inputs | 🟢 PASS |
| 16 | Telemetry & Health Matrix | Status Page `status.aiva.pe` activa | 🟢 PASS |
| 17 | **Secret Leakage Test** | Cero tokens, contraseñas o API keys en Git/logs | 🟢 PASS |
| 18 | **Failure Recovery Test** | Simulación de caída de Redis, MySQL y AI Provider | 🟢 PASS |
| 19 | **Rate Limit & Abuse Test** | Inmunidad ante spam de 10,000 peticiones webhook | 🟢 PASS |
| 20 | **Deployment Rollback Test** | Capacidad de rollback automatizado en fallo de deploy | 🟢 PASS |

---

### C7 — DonutsFs Pilot & Telemetría SLA de Latencia
- **Webhook ACK (Meta API):** $< 2$ segundos (Obligatorio).
- **Respuesta Conversacional de IA:**
  - **P50 (Mediana):** $< 2$ segundos.
  - **P95 (Casos complejos / OCR):** $< 5$ segundos.
  - **P99 (Límite máximo):** $< 10$ segundos.
- **AI Provider Failure Rate:** Medición independiente de fallos/timeouts de proveedores externos (Groq, OpenAI) para no atribuir fallos del proveedor al Core de AIVA.

---

### C8 — Checklist de Criterios de Salida del Piloto (Pilot Exit Criteria)
Lista de verificación en estado inicial (casillas pendientes `[ ]`) a ser marcadas únicamente durante la ejecución real:
- [ ] **Mínimo 7 a 14 días consecutivos** de operación comercial en vivo.
- [ ] **0 incidentes críticos** de caída del sistema.
- [ ] **0 violaciones de aislamiento multi-tenant**.
- [ ] **0 falsos positivos de comprobantes de pago** (Regla `MATCHED \neq VERIFIED` estricta).
- [ ] **100% de cumplimiento de la invariante matemática de stock**.
- [ ] **Workers de colas estables con 0 memoria filtrada (Memory Leaks)**.
- [ ] **Restauración de backup probada y certificada**.
- [ ] **Telemetría MTTA y MTTR cumplida dentro de los parámetros de SLA**.
- [ ] **Tasa de error (Error Rate) dentro del umbral definido ($< 0.5\%$)**.
- [ ] **Conversión comercial y tasa de Handover dentro del objetivo ($< 10\%$)**.

---

### C9 — Escalamiento Multi-Vertical (Zero Core Code Changes)
Demostración de versatilidad multi-vertical ajustando **únicamente configuración de tenant** sin alterar el código del Core:
1. **Tenant 1 (Pastelería - DonutsFs):** `vertical = bakery`, `stock_mode = daily`, `requires_advance = 24h`.
2. **Tenant 2 (Restaurante - Restaurante X):** `vertical = restaurant`, `stock_mode = menu_daily`, `delivery = express_zones`.
3. **Tenant 3 (Retail - Minimarket Z):** `vertical = retail`, `stock_mode = sku`, `catalog = categories`.

---

### C9.5 — Commercial Readiness & Unit Economics (Modelo de Costos SaaS por Tenant)
Cálculo automatizado de la rentabilidad comercial SaaS:
$$\text{COSTO\_VARIABLE} = \text{WhatsApp Meta API} + \text{Tokens LLM} + \text{Análisis OCR} + \text{Storage S3}$$
$$\text{COSTO\_COMPARTIDO} = \frac{\text{VPS} + \text{Redis} + \text{MySQL} + \text{Monitoring} + \text{Backups}}{\text{Número de Tenants}}$$
$$\text{MARGEN\_BRUTO\_TENANT} = \text{MRR\_TENANT} - (\text{COSTO\_VARIABLE} + \text{COSTO\_COMPARTIDO})$$

---

### C10 — AIVA Commerce v21.1 Public Release
Apertura comercial masiva de la plataforma SaaS.
