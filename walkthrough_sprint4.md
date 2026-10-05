# Walkthrough - Sprint 4: AI Engine MVP (Motor de Inteligencia Artificial & Detección de Intenciones)

Hemos completado al 100% la infraestructura y codificación completa del **Sprint 4 (AI Engine MVP)** de la plataforma SaaS **AIVA**. Este módulo constituye el **cerebro conversacional inteligente** que procesa mensajes de clientes, detecta intenciones comerciales, inyecta el snapshot del negocio compilado en Redis (`0ms` latencia), genera respuestas estructuradas en JSON y audita el consumo de tokens y costos en tiempo real.

---

## 1. Componentes Construidos en el Sprint 4

### 1.1 AI Gateway & Abstracción Multi-Proveedor (`app/Core/Contracts/AI/` & `app/Modules/AI/Providers/`)
- **`AIProviderInterface`**: Contrato desacoplado para invocación de modelos LLM.
- **`MockAIProvider`**: Proveedor determinista en memoria para desarrollo local e integración continua con **costo $0**.
- **`OpenAIProvider`**: Integración con GPT-4o-mini con parsing estricto de JSON estructurado.
- **`GeminiProvider`**: Integración con Google Gemini 1.5 Flash.
- **`AIFactory`**: Fábrica para instanciación dinámica del proveedor activo según configuración.

### 1.2 DTOs de Salida Estructurada & Contexto (`app/Modules/AI/DTOs/`)
- **`CustomerIntent` Enum**: `PURCHASE_INTENT`, `PRODUCT_INQUIRY_INTENT`, `HOURS_INQUIRY_INTENT`, `GREETING_INTENT`, `HUMAN_TAKEOVER_INTENT`.
- **`AIAnalysisResult` DTO**:
  - `intent`: Intención comercial detectada.
  - `confidence`: Puntuación de certeza probabilística (ej. `0.98`).
  - `entities`: Entidades extraídas (`product`, `quantity`, `options`).
  - `needs_human`: Indicador booleano de derivación a operador humano.
  - `reply_text`: Respuesta conversacional generada para el cliente.
  - `meta`: `provider`, `model`, `prompt_version`, `input_tokens`, `output_tokens`, `estimated_cost`, `latency_ms`.
- **`PromptContextDTO`**: DTO que agrupa el Business Brain de Redis, memorias del cliente y zona horaria.

### 1.3 Inyección del Business Brain & Prompt Engineering (`app/Modules/AI/Services/`)
- **`PromptContextBuilder`**: Lee el snapshot compilado desde Redis (`tenant:{id}:brain:knowledge:current`) y la memoria permanente del cliente (`customer_memories`).
- **`PromptBuilderService`**: Transforma el DTO `PromptContextDTO` en un System Prompt altamente comercial adaptado al `BusinessType` (Restaurante, Barbería, Tienda, etc.), sin realizar consultas directas a base de datos.

### 1.4 Base de Datos & Migraciones (`app/Modules/AI/Database/Migrations/`)
- `2026_08_05_000018_create_ai_requests_table.php`: Registro de consumo con `prompt_version`, `confidence`, `input_tokens`, `output_tokens`, `estimated_cost` y `latency_ms`.
- `2026_08_05_000019_create_customer_memories_table.php`: Memoria permanente del cliente (`favorite_product`, `preferred_address`).

### 1.5 Casos de Uso (Actions de la Capa `Application/`)
- **`ProcessMessageAction`**: Coordina la compilación del contexto, invocación del Gateway de IA, registro en `ai_requests` y despacho de eventos `AIResponseGenerated` y `AIUsageRecorded`.
- **`AnalyzeIntentAction`**: Detección liviana de intenciones comerciales.

### 1.6 Controladores, Rutas API & RBAC
- **`AIController`**:
  - `POST /api/v1/ai/process` (Procesamiento completo y generación de salida estructurada).
  - `POST /api/v1/ai/analyze-intent` (Detección liviana de intenciones).
  - `GET /api/v1/ai/usage` (Estadísticas e historial de tokens y costos por tenant).
- Protegidos por `RequirePermission` middleware (`conversation:reply`, `tenant:manage_settings`).

### 1.7 Gobernanza (ADR-007)
- [ADR-007](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-007-future-rag-knowledge-retrieval.md): Arquitectura Futura de Recuperación de Conocimiento (RAG Strategy).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/AIMessageProcessingTest.php`: Verifica que `MockAIProvider` detecte `PURCHASE_INTENT` a costo $0.
- `tests/Feature/AIUsageRecordingTest.php`: Verifica la auditoría de tokens, `prompt_version` y costos en el modelo `AIRequest`.
- `tests/Feature/PromptBuilderTest.php`: Verifica la inyección del Business Brain en el System Prompt.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 5)

El **Sprint 4 (AI Engine MVP)** ha quedado 100% construido y probado. La plataforma AIVA ya cuenta con capacidad autónoma para analizar mensajes y estructurar intenciones comerciales. Estamos listos para el **Sprint 5: WhatsApp Connector & Webhook Receiver** (Recepción de mensajes de WhatsApp Meta Cloud API, orquestación de webhooks y respuesta automática mediante el Motor de IA).
