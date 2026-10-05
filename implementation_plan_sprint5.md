# Arquitectura e Plan de Implementación v5.0 - Sprint 5: WhatsApp Connector & Webhook Receiver (Canal de Mensajería & Meta Cloud API)

## Resumen Ejecutivo

Esta versión **v5.0** del **Sprint 5** establece la arquitectura completa del **WhatsApp Connector & Webhook Receiver**. Introduce la separación entre **Estado Operativo (`ConversationStatus`: `OPEN`, `CLOSED`, `ARCHIVED`)** y **Modo de Atención (`ConversationMode`: `AI`, `HUMAN`, `HYBRID`)**, garantiza la **Idempotencia de Webhooks** con la tabla `processed_webhooks`, desacopla la lógica de negocio en el **`ConversationOrchestrator`**, aplica **Bloqueo Conversacional en Redis (`Conversation Lock`)** para prevenir colisiones de mensajes rápidos, y estructura campos nativos para archivos multimedia (`media_url`, `mime_type`, `file_size`).

---

## 1. Arquitectura de Orquestación Conversacional (v5.0)

```text
Cliente escribe en WhatsApp (Meta Cloud API)
                  │
                  ▼
HTTP POST /api/v1/whatsapp/webhook
                  │
                  ├─► Verifica Token & Firma HMAC-SHA256
                  ├─► Verifica Idempotencia (processed_webhooks table)
                  ├─► Responde 200 OK inmediatamente (< 100ms)
                  └─► Encola ProcessWhatsAppWebhookJob
                              │
                              ▼
                  ProcessWhatsAppWebhookJob
                              │
                              ▼
                  Redis Conversation Lock ("conversation:{uuid}")
                              │
                              ▼
                  ConversationOrchestrator
                              │
                              ├─► Resuelve Tenant & Cliente
                              ├─► Obtiene/Crea Conversation (Status: OPEN, Mode: AI)
                              ├─► Registra Message entrante (con media_url si aplica)
                              │
                              ├───► [Mode = AI]
                              │      └─► Invoca AI Engine (ProcessCustomerMessageAction - Sprint 4)
                              │      └─► Analiza AIAnalysisResult (intent, confidence, entities)
                              │      └─► Si needs_human = true ➔ Cambia Mode a HUMAN
                              │      └─► Si no ➔ Envia respuesta vía MetaWhatsAppClient & guarda Message
                              │
                              └───► [Mode = HUMAN]
                                     └─► Emite evento HumanTakeoverRequested
```

---

## 2. Componentes Clave del Módulo de Mensajería

### 2.1 Enums del Dominio
- **`ConversationStatus`**: `OPEN`, `CLOSED`, `ARCHIVED`.
- **`ConversationMode`**: `AI`, `HUMAN`, `HYBRID`.
- **`SenderType`**: `CUSTOMER`, `AI`, `AGENT`.
- **`MessageType`**: `TEXT`, `IMAGE`, `AUDIO`, `LOCATION`, `DOCUMENT`.
- **`MessageStatus`**: `RECEIVED`, `SENT`, `DELIVERED`, `READ`, `FAILED`.

### 2.2 `ConversationOrchestrator` Service
Ubicación: `app/Modules/Messaging/Services/ConversationOrchestrator.php`
- Componente central que desacopla la lógica del Job. Gestiona el ciclo de vida de la conversación, la deduplicación de mensajes, la invocación de la IA y el cambio de modo de atención (`AI` vs `HUMAN`).

### 2.3 Idempotencia & Bloqueo Conversacional
- **`processed_webhooks`**: Registra cada `event_id` o `wamid` procesado para descartar retribuciones de Meta.
- **Redis Conversation Lock**: Bloquea la clave `conversation:{tenant_id}:{customer_phone}` por 5 segundos mientras el orquestador procesa la respuesta, asegurando que mensajes consecutivos se procesen en orden estricto.

---

## 3. Esquema de Base de Datos (Sprint 5 v5.0)

### 3.1 Tabla `processed_webhooks` (Idempotencia)
```sql
CREATE TABLE processed_webhooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL UNIQUE,
    provider VARCHAR(50) NOT NULL DEFAULT 'META_WHATSAPP',
    processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Tabla `conversations` (Estado + Modo)
```sql
CREATE TABLE conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    customer_name VARCHAR(150) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN', -- OPEN, CLOSED, ARCHIVED
    mode VARCHAR(30) NOT NULL DEFAULT 'AI',     -- AI, HUMAN, HYBRID
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_customer_phone (tenant_id, customer_phone),
    INDEX idx_conversations_tenant_status_mode (tenant_id, status, mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `messages` (con campos para Multimedia)
```sql
CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(30) NOT NULL, -- CUSTOMER, AI, AGENT
    message_type VARCHAR(30) NOT NULL DEFAULT 'TEXT', -- TEXT, IMAGE, AUDIO, LOCATION
    content TEXT NOT NULL,
    media_url VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size UNSIGNED INT NULL,
    external_id VARCHAR(100) NULL, -- wamid de Meta
    status VARCHAR(30) NOT NULL DEFAULT 'RECEIVED', -- RECEIVED, SENT, DELIVERED, READ, FAILED
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_messages_conversation (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Plan de Ejecución Inmediato

### Paso 1: Enums & Interfaces (`Core/`)
- Enums: `ConversationStatus` (`OPEN`, `CLOSED`, `ARCHIVED`), `ConversationMode` (`AI`, `HUMAN`, `HYBRID`), `SenderType`, `MessageType`, `MessageStatus`.
- Interfaces: `WhatsAppClientInterface`.
- DTOs: `IncomingWhatsAppMessageDTO`, `OutgoingWhatsAppMessageDTO`.
- Domain Events: `ConversationStarted`, `MessageReceived`, `MessageSent`, `HumanTakeoverRequested`.

### Paso 2: Migraciones & Modelos (`Messaging Module`)
- Migración `2026_08_05_000020_create_processed_webhooks_table.php`.
- Migración `2026_08_05_000021_create_conversations_table.php`.
- Migración `2026_08_05_000022_create_messages_table.php`.
- Modelos Eloquent: `ProcessedWebhook`, `Conversation` y `Message`.

### Paso 3: Repositorios, Clients & Orchestrator
- `ConversationRepository` y `MessageRepository`.
- `MockWhatsAppClient` (costo $0 para desarrollo y tests).
- `MetaWhatsAppClient` (API REST Graph de Meta).
- `ConversationOrchestrator` Service.

### Paso 4: Controller & Job de Procesamiento Asíncrono
- `WhatsAppWebhookController`:
  - `GET /api/v1/whatsapp/webhook` (Verificación de desafío `hub.verify_token`).
  - `POST /api/v1/whatsapp/webhook` (Recepción con comprobación de idempotencia en `processed_webhooks` y respuesta inmediata `200 OK`).
- `ProcessWhatsAppWebhookJob`: Adquiere el lock en Redis de la conversación y delega la ejecución al `ConversationOrchestrator`.

### Paso 5: Suite de Pruebas Automatizadas
- `WhatsAppWebhookVerificationTest`: Prueba el desafío GET de verificación de Meta.
- `WhatsAppConversationFlowTest`: Simula el webhook de Meta, valida idempotencia, lock en Redis, respuesta automática de la IA con `MockWhatsAppClient` e inserción en BD.
- `HumanTakeoverTest`: Verifica el cambio de modo de `AI` a `HUMAN` cuando `needs_human = true`.
