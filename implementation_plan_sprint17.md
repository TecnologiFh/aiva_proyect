# Arquitectura e Plan de Implementación v17.0 - Sprint 17: AI Voice Employee & Unified Conversation Kernel (Empleado Digital de Voz 24/7 & Núcleo Conversacional Omnicanal)

## Resumen Ejecutivo

Esta versión **v17.0** del **Sprint 17** reimagina el módulo como el **AI Voice Employee (Empleado Digital de Voz)** de AIVA. AIVA contesta el teléfono del negocio las 24 horas del día, atiende llamadas entrantes, toma pedidos en tiempo real, resuelve objeciones y realiza llamadas salientes.

Incorpora el **Unified Conversation Kernel (Núcleo Conversacional Omnicanal)**: un motor transversal donde **WhatsApp**, **Llamadas Telefónicas de Voz**, **Instagram**, **Facebook Messenger**, **Telegram** y **WebChat** convergen en el mismo cerebro sin reescribir la lógica de negocio.

Incluye orquestación de voz con **`VoiceSession`** (interrupciones, silencios, streaming de audio), **`Semantic Call Memory`**, **`CallOutcome`**, desgloses financieros de costos STT/TTS y transferencia en tiempo real al **Team Inbox** (Sprint 13) con **Live Assist**.

---

## 1. Flujo de Arquitectura del AI Voice Employee & Unified Conversation Kernel

```text
Entrada de Canal (WhatsApp / Llamada de Voz / Instagram / WebChat)
                          │
                          ▼
            Unified Conversation Kernel (Núcleo Único Omnicanal)
                          │
  ┌───────────────────────┴────────────────────────┐
  ▼                                                ▼
Llamada Telefónica (Voz)                      Mensajería Escrita
  │                                                │
  ▼                                                ▼
STT Parcial en Streaming                      Ingesta de Texto
  │                                                │
  └───────────────────────┬────────────────────────┘
                          │
                          ▼
               Conversation Manager & VoiceSession
               (Interrupciones, Silencios, Intent Engine, Contexto)
                          │
                          ▼
               Business Brain en Redis (0ms) & Order Engine (Sprint 6)
                          │
                          ▼
               Response Planner (Redacción de Respuesta & Acciones)
                          │
  ┌───────────────────────┴────────────────────────┐
  ▼                                                ▼
TTS Parcial en Streaming (Voz)                Respuesta de Texto
  │                                                │
  ▼                                                ▼
Voz Hiperrealista al Cliente                  WhatsApp / WebChat
                          │
                          ▼
              Semantic Call Memory & CallOutcome Logging
              ├─► Transcripción + Resumen + Motivos de Compra/Objeciones
              ├─► Costo Financiero (input/output tokens, stt/tts seconds)
              └─► Transferencia a Team Inbox (Sprint 13) si requiere humano
```

---

## 2. Componentes Clave del Empleado Digital de Voz

### 2.1 Enums del Dominio de Voz & Resultados
- **`CallDirection`**: `INBOUND`, `OUTBOUND`.
- **`CallStatus`**: `QUEUED`, `RINGING`, `IN_PROGRESS`, `COMPLETED`, `FAILED`, `BUSY`, `NO_ANSWER`.
- **`VoiceProvider`**: `OPENAI_REALTIME`, `ELEVENLABS`, `TWILIO_MEDIA_STREAM`.
- **`CallOutcome`**: `ORDER_CREATED`, `PAYMENT_CONFIRMED`, `NO_INTEREST`, `COMPLAINT`, `SUPPORT`, `CALLBACK_REQUEST`, `TRANSFER_TO_AGENT`.

### 2.2 Administrador de Sesión de Voz (`VoiceSessionManager` & `VoiceSession`)
- Rastrea en memoria/Redis la sesión activa de voz (`session_id`, `confirmation_stage`, `interruptions`, `silence_counter`, `pending_order`).

### 2.3 Memoria Semántica de Llamada & Métricas Financieras (`SemanticCallMemory`)
- Registra no solo la transcripción, sino intenciones, objeciones del cliente, costo de STT/TTS (`stt_seconds`, `tts_seconds`, `provider_cost`) y margen estimado.

---

## 3. Esquema de Base de Datos (Sprint 17 v17.0)

### 3.1 Tabla `voice_calls` (Auditoría, Transcripción & Métricas Financieras)
```sql
CREATE TABLE voice_calls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    direction VARCHAR(20) NOT NULL DEFAULT 'INBOUND', -- INBOUND, OUTBOUND
    caller_phone VARCHAR(30) NOT NULL,
    receiver_phone VARCHAR(30) NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'TWILIO_MEDIA_STREAM',
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'QUEUED', -- QUEUED, IN_PROGRESS, COMPLETED, FAILED...
    outcome VARCHAR(50) NOT NULL DEFAULT 'ORDER_CREATED', -- CallOutcome Enum
    recording_url TEXT NULL,
    transcript TEXT NULL,
    semantic_memory JSON NULL, -- Intenciones, objeciones, preferencias detectadas
    stt_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    tts_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    provider_cost DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_voice_calls_outcome (tenant_id, outcome, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular (`app/Modules/Voice/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── CallDirection.php
│   │       ├── CallStatus.php
│   │       ├── VoiceProvider.php
│   │       └── CallOutcome.php
│   │
│   ├── Modules/
│   │   └── Voice/
│   │       ├── Application/
│   │       │   ├── InitiateVoiceCall/
│   │       │   │   ├── InitiateVoiceCallAction.php
│   │       │   │   ├── InitiateVoiceCallInput.php
│   │       │   │   └── InitiateVoiceCallResult.php
│   │       │   └── ProcessInboundVoiceCall/
│   │       │       ├── ProcessInboundVoiceCallAction.php
│   │       │       └── ProcessInboundVoiceCallInput.php
│   │       ├── Controllers/
│   │       │   └── VoiceController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000044_create_voice_calls_tables.php
│   │       ├── Models/
│   │       │   └── VoiceCall.php
│   │       ├── Repositories/
│   │       │   └── VoiceCallRepository.php
│   │       ├── Services/
│   │       │   ├── VoiceAIEngineService.php
│   │       │   ├── VoiceSessionManager.php
│   │       │   └── UnifiedConversationKernel.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Voice Module`)
- Enums: `CallDirection`, `CallStatus`, `VoiceProvider`, `CallOutcome`.
- Migración `2026_08_06_000044_create_voice_calls_tables.php`.
- Modelo Eloquent: `VoiceCall`.

### Paso 2: Unified Kernel & Orquestador de Sesión (`Services/`)
- `VoiceCallRepository`.
- `UnifiedConversationKernel`: Núcleo conversacional omnicanal transversal.
- `VoiceSessionManager`: Control de estado de llamadas en tiempo real (interrupciones, silencios, confirmación).
- `VoiceAIEngineService`: Invocación de llamadas, STT/TTS streaming y cálculo financiero de costos.

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`InitiateVoiceCallAction`**: Inicia una llamada telefónica saliente con el Empleado Digital de Voz.
- **`ProcessInboundVoiceCallAction`**: Procesa la interacción de voz y genera la respuesta conversacional en streaming.

### Paso 4: Controladores, Rutas & RBAC
- `VoiceController`:
  - `POST /api/v1/voice/initiate` (Disparar llamada saliente con el Empleado Digital).
  - `POST /api/v1/voice/webhook/events` (Recepción de eventos de voz WebRTC / Twilio).
  - `GET /api/v1/voice/calls/{uuid}/transcript` (Transcripción, memoria semántica y desglose financiero).
- Protegidos mediante `RequirePermission` middleware (`crm:edit`, `crm:view`).

### Paso 5: Suite de Pruebas Automatizadas
- `VoiceSessionManagerTest`: Control de interrupciones, silencios y contexto de llamada.
- `UnifiedConversationKernelTest`: Orquestación unificada para WhatsApp y Voz.
- `VoiceCostCalculatorTest`: Auditoría financiera de costos STT, TTS y margen.
