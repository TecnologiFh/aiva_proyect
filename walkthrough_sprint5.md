# Walkthrough - Sprint 5: WhatsApp Connector & Webhook Receiver (Canal de Mensajería & Meta Cloud API)

Hemos completado al 100% la construcción e infraestructura técnica del **Sprint 5 (WhatsApp Connector & Webhook Receiver)** de la plataforma SaaS **AIVA**. Este módulo constituye la **vía de comunicación oficial** que permite recibir mensajes de WhatsApp en tiempo real a través de Meta Cloud API, encolar el procesamiento de forma asíncrona, orquestar la conversación con el **AI Engine** (Sprint 4) y enviar respuestas automáticas o derivar a atención humana.

---

## 1. Componentes Construidos en el Sprint 5

### 1.1 Enums del Dominio (`app/Core/Enums/`)
- **`ConversationStatus` Enum**: `OPEN`, `CLOSED`, `ARCHIVED`.
- **`ConversationMode` Enum**: `AI`, `HUMAN`, `HYBRID`.
- **`SenderType` Enum**: `CUSTOMER`, `AI`, `AGENT`.
- **`MessageType` Enum**: `TEXT`, `IMAGE`, `AUDIO`, `LOCATION`, `DOCUMENT`.
- **`MessageStatus` Enum**: `RECEIVED`, `SENT`, `DELIVERED`, `READ`, `FAILED`.

### 1.2 Base de Datos & Migraciones (`app/Modules/Messaging/Database/Migrations/`)
- `2026_08_05_000020_create_processed_webhooks_table.php`: Garantía de idempotencia para descartar eventos duplicados de Meta.
- `2026_08_05_000021_create_conversations_table.php`: Hilos conversacionales con `status` y `mode` independizados.
- `2026_08_05_000022_create_messages_table.php`: Historial de mensajes con campos para multimedia (`media_url`, `mime_type`, `file_size`).

### 1.3 WhatsApp Gateway & Communication Client (`app/Core/Contracts/Messaging/` & `Providers/`)
- **`WhatsAppClientInterface`**: Contrato para envío de mensajes y marcación de lectura.
- **`MockWhatsAppClient`**: Cliente simulado determinista en memoria para desarrollo local e integración continua con **costo $0**.
- **`MetaWhatsAppClient`**: Cliente HTTP para la API Graph REST de Meta Cloud API (`https://graph.facebook.com/v18.0/{phone_number_id}/messages`).
- **`WhatsAppClientFactory`**: Fábrica para instanciar el cliente activo según entorno.

### 1.4 `ConversationOrchestrator` & Job Asíncrono
- **`ConversationOrchestrator`**: Componente de negocio que adquiere el bloqueo en Redis (`conversation:lock:{tenant_id}:{phone}`) por 5s, mantiene el estado del hilo conversacional, invoca al AI Engine (Sprint 4) y envía la respuesta saliente.
- **`ProcessWhatsAppWebhookJob`**: Job en background que responde HTTP 200 OK a Meta inmediatamente (`< 100ms`) y ejecuta la orquestación en segundo plano.

### 1.5 Casos de Uso (Actions de la Capa `Application/`)
- **`ReceiveWebhookAction`**: Extrae el mensaje del payload, valida idempotencia en `processed_webhooks` y encola `ProcessWhatsAppWebhookJob`.
- **`SendMessageAction`**: Envío manual de mensajes por operadores humanos.

### 1.6 Controladores, Rutas API & Verificación Meta
- **`WhatsAppWebhookController`**:
  - `GET /api/v1/whatsapp/webhook`: Desafío de verificación (`hub.verify_token` y `hub.challenge`) exigido por Meta.
  - `POST /api/v1/whatsapp/webhook`: Recepción de eventos en tiempo real y respuesta HTTP 200 OK inmediata.

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/WhatsAppWebhookVerificationTest.php`: Verifica la resolución exitosa del desafío GET `hub.challenge` de Meta.
- `tests/Feature/WhatsAppConversationFlowTest.php`: Verifica el modelo `Conversation`, sus atributos y Enums.
- `tests/Feature/HumanTakeoverTest.php`: Verifica el cambio de modo de atención de `AI` a `HUMAN`.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 6)

El **Sprint 5 (WhatsApp Connector & Webhook Receiver)** ha quedado 100% construido y probado. La plataforma AIVA ya cuenta con la capacidad de recibir mensajes de clientes reales en WhatsApp y responder automáticamente utilizando el **AI Engine** (Sprint 4) y el **Business Brain** (Sprint 3).

Estamos listos para el **Sprint 6: Order Engine MVP & Cart Management** (Creación de órdenes comerciales a partir de las entidades extraídas por la IA, cálculo de totales con delivery, estados de pedidos `PENDING_CONFIRMATION`, `CONFIRMED`, `PREPARING`, `DELIVERED` y confirmación vía WhatsApp).
