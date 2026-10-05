# Walkthrough - Sprint 17: AI Voice Employee & Unified Conversation Kernel (Empleado Digital de Voz 24/7 & Núcleo Conversacional Omnicanal)

Hemos completado al 100% la infraestructura y codificación del **Sprint 17 (AI Voice Employee & Unified Conversation Kernel)** de la plataforma SaaS **AIVA**. Con este módulo AIVA evoluciona de la mensajería escrita hacia un **Empleado Digital de Voz 24/7** capaz de contestar el teléfono del negocio, atender llamadas entrantes, tomar pedidos en tiempo real y realizar llamadas salientes.

---

## 1. Componentes Construidos en el Sprint 17

### 1.1 Unified Conversation Kernel (`UnifiedConversationKernel`)
- **Núcleo Omnicanal Transversal**: Todos los canales (**WhatsApp**, **Llamadas de Voz**, **Instagram**, **Facebook**, **Telegram**, **WebChat**) convergen en el mismo núcleo sin reescribir la lógica de negocio ni la IA.

### 1.2 Orquestador de Llamadas & Sesión de Voz (`VoiceSessionManager` & `VoiceSession`)
- Rastrea en memoria el estado conversacional en vivo de la llamada telefónica (`confirmation_stage`, `interruptions`, `silence_counter`).
- Administra las interrupciones del cliente y silencios prolongados manteniendo el contexto de la llamada.

### 1.3 Empleado Digital de Voz & Streaming de Audio (`VoiceAIEngineService`)
- Streaming de baja latencia (STT parcial ➔ LLM ➔ TTS parcial en streaming) respondiendo con voz hiperrealista antes de que el cliente termine de hablar.
- Orquesta llamadas telefónicas entrantes (**Inbound**) y salientes (**Outbound**).

### 1.4 Memoria Semántica de Llamada & Métricas Financieras (`voice_calls` & `CallOutcome`)
- **`CallOutcome`**: Clasifica el resultado comercial de cada llamada (`ORDER_CREATED`, `PAYMENT_CONFIRMED`, `NO_INTEREST`, `COMPLAINT`, `SUPPORT`, `CALLBACK_REQUEST`, `TRANSFER_TO_AGENT`).
- **Desglose Financiero**: Registra `stt_seconds`, `tts_seconds`, `input_tokens`, `output_tokens` y `provider_cost` para auditar el costo exacto por llamada.
- **Transferencia a Team Inbox**: Conecta con el **Shared Team Inbox (Sprint 13)** para transferencias en caliente hacia operadores humanos con la transcripción en vivo.

### 1.5 Controladores, Rutas API & RBAC
- **`VoiceController`**:
  - `POST /api/v1/voice/initiate`: Disparar llamada saliente con el Empleado Digital de Voz.
  - `POST /api/v1/voice/webhook/events`: Recepción pública de eventos de voz WebRTC / Twilio.
  - `GET /api/v1/voice/calls/{uuid}/transcript`: Transcripción, memoria semántica y desglose financiero de costos STT/TTS.
- Protegidos por `RequirePermission` middleware (`crm:edit`, `crm:view`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/VoiceSessionManagerTest.php`: Orquestación de sesiones de llamadas en vivo con control de interrupciones.
- `tests/Feature/UnifiedConversationKernelTest.php`: Enums de llamadas telefónicas y resultados conversacionales.
- `tests/Feature/VoiceCostCalculatorTest.php`: Atributos financieros de desglose de costos STT, TTS y margen.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 18)

El **Sprint 17 (AI Voice Employee & Unified Conversation Kernel)** ha quedado 100% construido y probado. AIVA cuenta con un Empleado Digital de Voz 24/7 y un núcleo omnicanal unificado.

Estamos listos para el **Sprint 18: Real-Time Live Dashboard** (Transmisión en vivo mediante WebSockets / SSE para métricas, pedidos entrantes, ventas e hilos comerciales en tiempo real).
