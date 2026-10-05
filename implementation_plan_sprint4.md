# Arquitectura e Plan de Implementación v4.0 - Sprint 4: AI Engine MVP (Motor de Inteligencia Artificial & Detección de Intenciones)

## Resumen Ejecutivo

Esta versión **v4.0** del **Sprint 4** establece el diseño del **AI Engine MVP**, integrando **Salida Estructurada (Structured Output)** con extracción de entidades e intenciones, **confianza probabilística (`confidence`)**, desacoplamiento de construcción de contexto (`PromptContextBuilder`) frente a generación de texto (`PromptBuilder`), trazabilidad por versión de prompt (`prompt_version`) y diferenciación entre **memoria permanente del cliente (`customer_memories`)** y **contexto temporal de chat (`conversation_context`)**.

---

## 1. Componentes de Arquitectura del Motor de IA (v4.0)

### 1.1 Flujo de Ejecución de Petición de IA
```text
Mensaje del Cliente
      │
      ▼
PromptContextBuilder (Reúne Business Brain de Redis, Contexto Temporal & Memoria Permanente)
      │
      ▼
PromptBuilder (Genera System Prompt final a partir del PromptContext DTO)
      │
      ▼
AI Gateway (AIProviderInterface) ──► MockAIProvider (Desarrollo/Tests $0)
                                 ──► OpenAIProvider / GeminiProvider
      │
      ▼
AIAnalysisResult DTO (Salida Estructurada)
┌────────────────────────────────────────────────────────┐
│ • intent: PURCHASE_INTENT                              │
│ • confidence: 0.98                                     │
│ • entities: [{"product": "Pizza", "quantity": 2, ...}] │
│ • needs_human: false                                   │
│ • reply_text: "¡Excelente elección! 2 Pizzas..."       │
└────────────────────────────────────────────────────────┘
      │
      ▼
AI Usage Recording (ai_requests table con prompt_version)
```

### 1.2 DTO de Salida Estructurada (`AIAnalysisResult`)
Ubicación: `app/Modules/AI/DTOs/AIAnalysisResult.php`
```php
namespace App\Modules\AI\DTOs;

class AIAnalysisResult
{
    public function __construct(
        public readonly string $intent,
        public readonly float $confidence,
        public readonly array $entities,
        public readonly bool $needsHuman,
        public readonly string $replyText,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly float $estimatedCost = 0.000000,
        public readonly int $latencyMs = 0
    ) {}
}
```

---

## 2. Esquema de Base de Datos (Sprint 4 v4.0)

### 2.1 Tabla `ai_requests` (Auditoría con `prompt_version`)
```sql
CREATE TABLE ai_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    provider VARCHAR(50) NOT NULL, -- OPENAI, GEMINI, MOCK
    model VARCHAR(50) NOT NULL,    -- gpt-4o-mini, gemini-1.5-flash
    prompt_version VARCHAR(20) NOT NULL DEFAULT 'v1.0',
    intent_detected VARCHAR(50) NULL,
    confidence DECIMAL(4, 3) NOT NULL DEFAULT 1.000,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost DECIMAL(10, 6) NOT NULL DEFAULT 0.000000,
    latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'SUCCESS',
    created_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_ai_requests_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabla `customer_memories` (Memoria Permanente del Cliente)
```sql
CREATE TABLE customer_memories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    memory_key VARCHAR(100) NOT NULL, -- favorite_product, preferred_address
    memory_value TEXT NOT NULL,
    importance_score TINYINT UNSIGNED NOT NULL DEFAULT 1,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tenant_customer_memory (tenant_id, customer_phone, memory_key),
    INDEX idx_customer_memories (tenant_id, customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Plan de Ejecución Inmediato

### Paso 1: Gobernanza & ADR-007 (`docs/adr/`)
- Crear `docs/adr/ADR-007-future-rag-knowledge-retrieval.md` (Documentación viva para integración RAG futura).

### Paso 2: Enums & DTOs (`Core/Enums/` & `AI/DTOs/`)
- Enum `CustomerIntent`: `PURCHASE_INTENT`, `PRODUCT_INQUIRY_INTENT`, `HOURS_INQUIRY_INTENT`, `GREETING_INTENT`, `HUMAN_TAKEOVER_INTENT`.
- DTOs: `PromptContextDTO`, `AIAnalysisResult`, `IntentAnalysisDTO`.

### Paso 3: Migraciones & Modelos (`AI Module`)
- `2026_08_05_000018_create_ai_requests_table.php` (con `prompt_version` y `confidence`).
- `2026_08_05_000019_create_customer_memories_table.php`.
- Modelos Eloquent: `AIRequest` y `CustomerMemory`.

### Paso 4: AI Gateway & Services
- `AIProviderInterface`.
- `MockAIProvider` (para ejecuciones en entorno local y testing sin costo API).
- `OpenAIProvider` y `GeminiProvider`.
- `PromptContextBuilder` (reúne el snapshot de Redis y memoria del cliente).
- `PromptBuilderService` (construye el System Prompt final).

### Paso 5: Casos de Uso (Actions de la Capa `Application/`)
- `ProcessCustomerMessageAction`: Coordina el contexto, ejecuta la IA, parsea la salida estructurada, registra el uso en `ai_requests` y emite el evento `AIResponseGenerated`.
- `AnalyzeIntentAction`: Detección liviana de intenciones.

### Paso 6: Controladores, Rutas API & RBAC
- `AIController`:
  - `POST /api/v1/ai/process` (Procesar mensaje y generar salida estructurada).
  - `POST /api/v1/ai/analyze-intent` (Analizar intención).
  - `GET /api/v1/ai/usage` (Historial y costo acumulado de tokens por tenant).
- Protegidos mediante `RequirePermission` middleware.

### Paso 7: Pruebas Automatizadas
- `AIMessageProcessingTest`: Valida el flujo completo con `MockAIProvider` verificando `PURCHASE_INTENT` y la extracción de `entities`.
- `PromptContextBuilderTest`: Revisa la integración del JSON de Redis en el contexto.
- `AIUsageRecordingTest`: Revisa el registro de `prompt_version`, `input_tokens`, `output_tokens` y `latency_ms`.
