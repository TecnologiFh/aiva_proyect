# Arquitectura e Plan de Implementación v13.0 - Sprint 13: Shared Team Inbox & Hybrid Support Hub (Bandeja de Entrada Compartida & Hub de Atención Híbrida)

## Resumen Ejecutivo

Esta versión **v13.0** transforma el módulo en el **Shared Team Inbox** (Hub Operativo Central). Convierte la conversación en el punto de encuentro unificado que conecta **Cliente + Pedidos + Pagos + Motor de IA + Inventario + CRM + Automatizaciones + Campañas**.

Distingue entre **Modo de Conversación** (`ConversationMode`: `AI` vs `HUMAN`), **Estado Operativo** (`ConversationStatus`: `OPEN`, `WAITING_PAYMENT`, `RESOLVED`, `CLOSED`), **Prioridades** (`LOW`, `URGENT`), **Métricas de SLA** (`first_response_time`, `resolution_time`), **Sugerencia y Resumen de IA** al operador, transferencias auditadas (`conversation_transfers`), observadores (`conversation_followers`) y notas internas con menciones (`@agente`).

---

## 1. Flujo de Arquitectura del Shared Team Inbox

```text
Entrada de Mensaje / Evento Comercial
                 │
                 ▼
       Conversation Hub (Punto Central)
                 │
                 ├─► ConversationMode: AI ──► Responde el Engine (Sprint 4)
                 │                                   │
                 └─► ConversationMode: HUMAN ────────┴─► Resumen IA 1-Click
                                                             │
                                                             ▼
                                                Team Inbox Operator Workspace
                                                ┌───────────────────────────────────┐
                                                │ • Sugerencias de Respuesta IA     │
                                                │ • SLA Timestamps & Priority       │
                                                │ • Notas con menciones @Ana        │
                                                │ • Transferencias & Followers      │
                                                └───────────────────────────────────┘
```

---

## 2. Componentes Clave del Shared Team Inbox

### 2.1 Enums del Dominio
- **`ConversationMode`**: `AI`, `HUMAN`, `HYBRID`.
- **`ConversationStatus`**: `OPEN`, `PENDING`, `WAITING_CUSTOMER`, `WAITING_PAYMENT`, `WAITING_DELIVERY`, `RESOLVED`, `CLOSED`.
- **`ConversationPriority`**: `LOW`, `NORMAL`, `HIGH`, `URGENT`.
- **`AgentStatus`**: `AVAILABLE`, `BUSY`, `OFFLINE`.

### 2.2 Asistente de IA para el Operador (Copilot Híbrido)
- **AI Suggested Reply**: La IA sugiere la respuesta adecuada con 1 clic.
- **AI Conversation Summary**: Genera un resumen ejecutivo instantáneo de 3 líneas cuando un operador toma el chat.
- **Handover back to AI**: El operador devuelve el chat a la IA inyectándole notas de contexto.

---

## 3. Esquema de Base de Datos (Sprint 13 v13.0)

### 3.1 Actualización a `conversations` (SLA, Prioridad, Estado & Resumen IA)
```sql
ALTER TABLE conversations
ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
ADD COLUMN first_response_at TIMESTAMP NULL,
ADD COLUMN last_response_at TIMESTAMP NULL,
ADD COLUMN closed_at TIMESTAMP NULL,
ADD COLUMN ai_summary TEXT NULL,
ADD INDEX idx_conv_status_priority (tenant_id, status, priority);
```

### 3.2 Tabla `agent_profiles` (Perfiles de Agentes del Team Inbox)
```sql
CREATE TABLE agent_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE', -- AVAILABLE, BUSY, OFFLINE
    max_concurrent_chats INT UNSIGNED NOT NULL DEFAULT 5,
    active_chats_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_agent_profiles_status (tenant_id, status, active_chats_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Tabla `conversation_transfers` (Historial Auditado de Transferencias)
```sql
CREATE TABLE conversation_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    from_agent_id BIGINT UNSIGNED NULL, -- NULL si viene de la IA
    to_agent_id BIGINT UNSIGNED NULL, -- NULL si vuelve a la IA
    reason VARCHAR(255) NULL,
    transferred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_transfers_conv (conversation_id, transferred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Tabla `conversation_followers` (Observadores / Supervisores)
```sql
CREATE TABLE conversation_followers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    followed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agent_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY uk_followers (conversation_id, agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 Tabla `conversation_notes` (Notas Internas con Menciones)
```sql
CREATE TABLE conversation_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    mentions JSON NULL, -- ["@Ana", "@Pedro"]
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agent_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.6 Tabla `canned_responses` (Respuestas Rápidas por Atajo & Categoría)
```sql
CREATE TABLE canned_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    shortcut VARCHAR(50) NOT NULL, -- /gracias, /horarios, /cuentas
    content TEXT NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'GENERAL', -- VENTAS, POSTVENTA, COBROS
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_canned_shortcut (tenant_id, shortcut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular del Shared Team Inbox (`app/Modules/AgentConsole/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── ConversationMode.php
│   │       ├── ConversationStatus.php
│   │       ├── ConversationPriority.php
│   │       └── AgentStatus.php
│   │
│   ├── Modules/
│   │   └── AgentConsole/
│   │       ├── Application/
│   │       │   ├── AssignConversation/
│   │       │   │   ├── AssignConversationAction.php
│   │       │   │   ├── AssignConversationInput.php
│   │       │   │   └── AssignConversationResult.php
│   │       │   ├── TransferConversation/
│   │       │   │   ├── TransferConversationAction.php
│   │       │   │   └── TransferConversationInput.php
│   │       │   ├── AddInternalNote/
│   │       │   │   ├── AddInternalNoteAction.php
│   │       │   │   └── AddInternalNoteInput.php
│   │       │   ├── ToggleConversationMode/
│   │       │   │   ├── ToggleConversationModeAction.php
│   │       │   │   └── ToggleConversationModeInput.php
│   │       │   └── GenerateAISuggestedReply/
│   │       │       ├── GenerateAISuggestedReplyAction.php
│   │       │       └── GenerateAISuggestedReplyResult.php
│   │       ├── Controllers/
│   │       │   └── AgentConsoleController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000040_create_agent_console_tables.php
│   │       ├── Models/
│   │       │   ├── AgentProfile.php
│   │       │   ├── ConversationAssignment.php
│   │       │   ├── ConversationTransfer.php
│   │       │   ├── ConversationFollower.php
│   │       │   ├── ConversationNote.php
│   │       │   └── CannedResponse.php
│   │       ├── Repositories/
│   │       │   ├── AgentProfileRepository.php
│   │       │   └── CannedResponseRepository.php
│   │       ├── Services/
│   │       │   ├── AgentRoutingService.php
│   │       │   └── AICopilotService.php
│   │       ├── Requests/
│   │       │   └── AddInternalNoteRequest.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`AgentConsole Module`)
- Enums: `ConversationMode`, `ConversationStatus`, `ConversationPriority`, `AgentStatus`.
- Migración `2026_08_06_000040_create_agent_console_tables.php`.
- Modelos Eloquent: `AgentProfile`, `ConversationAssignment`, `ConversationTransfer`, `ConversationFollower`, `ConversationNote`, `CannedResponse`.

### Paso 2: Repositorios & Copiloto IA (`Services/`)
- `AgentProfileRepository` & `CannedResponseRepository`.
- `AgentRoutingService` (Asignación inteligente por carga).
- `AICopilotService` (Resumen automático de 3 líneas y sugerencia de respuestas).

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`ToggleConversationModeAction`**: Alterna entre `AI` y `HUMAN` conservando resumen de contexto.
- **`AssignConversationAction`**: Asigna chat a un operador con registro de auditoría.
- **`TransferConversationAction`**: Transfiere chat entre agentes o hacia la IA con `ConversationTransfer`.
- **`AddInternalNoteAction`**: Notas internas con soporte de menciones (`@Ana`).
- **`GenerateAISuggestedReplyAction`**: Genera sugerencia de respuesta de la IA en 1 clic.

### Paso 4: Controladores, Rutas & RBAC
- `AgentConsoleController`:
  - `GET /api/v1/agent-console/inbox` (Bandeja de entrada filtrada por estado, prioridad y asignación).
  - `POST /api/v1/agent-console/mode` (Cambiar modo a HUMANO / IA con resumen).
  - `POST /api/v1/agent-console/transfer` (Transferir conversación).
  - `POST /api/v1/agent-console/notes` (Agregar nota interna con mención).
  - `GET /api/v1/agent-console/ai-suggested-reply` (Sugerencia de respuesta IA 1-click).
- Protegidos mediante `RequirePermission` middleware (`crm:view`, `crm:edit`).

### Paso 5: Suite de Pruebas Automatizadas
- `AgentRoutingTest`: Asignación automática al operador menos ocupado.
- `ConversationTransferAuditTest`: Historial de transferencias entre operadores e IA.
- `AICopilotServiceTest`: Resumen ejecutivo automático de conversación.

---

## User Review Required

> [!IMPORTANT]
> **Hub de Atención Unificado**: La conversación actúa como el centro operativo que consume información de Pedidos, Pagos, Inventario, CRM y Automatizaciones en un solo panel para el operador humano. Por favor revisa la propuesta antes de iniciar la ejecución.

---

## Verification Plan

### Automated Tests
1. **`AgentRoutingTest`**: Probar asignación por `Round-Robin` / `Least-Busy`.
2. **`ConversationTransferAuditTest`**: Verificar creación de registros en `conversation_transfers`.
3. **`AICopilotServiceTest`**: Probar generación del resumen inteligente de la conversación.

### Manual Verification
1. Petición HTTP a `/api/v1/agent-console/inbox` y transferencia entre dos operadores.
