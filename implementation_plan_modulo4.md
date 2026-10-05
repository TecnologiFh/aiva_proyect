# Plan de Implementación — Módulo 4: AI Guardian & Reconstrucción de Contexto 🧠

El Módulo 3 blindó nuestra frontera. Recibimos payloads puros y seguros a través de nuestro webhook, deduplicados y aislados, y los dejamos descansando en una cola asíncrona (`ProcessWhatsAppMessageJob`). 
El Módulo 4 es el despertar del cerebro. Aquí rehidrataremos el contexto del Tenant en un entorno aislado, organizaremos el historial de chat y dejaremos que la Inteligencia Artificial tome el control utilizando las herramientas transaccionales que construimos en el Módulo 2.

## User Review Required

> [!WARNING]  
> **Modelado de Conversaciones**  
> Para que el LLM tenga memoria, necesitamos persistir el historial de mensajes. La estrategia clásica es crear las entidades `Customer` (El usuario final del comercio), `Conversation` (Hilo de chat) y `Message` (Historial). ¿Estás de acuerdo con añadir estas tablas físicas, o prefieres delegar el historial temporalmente a Redis/Caché para el MVP? Recomendación: Tablas físicas para poder hacer analítica posterior.

> [!IMPORTANT]  
> **LLM Provider (El Cerebro)**  
> Todo el sistema de herramientas (Function Calling) depende del motor de inferencia. Asumo que para AIVA utilizaremos OpenAI (GPT-4o) dada su fiabilidad extrema llamando funciones JSON. ¿Deseas inyectar una abstracción (Ej: `LLMProviderInterface`) o vamos directamente a construir la conexión estricta con OpenAI para acelerar el MVP?

## Proposed Changes

### 1. Modelos de Dominio Conversacional (DB & Eloquent)
Se crearán las entidades para gestionar quién habla y qué se ha dicho, siempre ancladas al Tenant.

#### [NEW] `database/migrations/2026_08_11_xxxx_create_customers_table.php`
- `phone_number` (String, Indexed)
- `name` (String, Nullable - extraído del profile de WhatsApp)

#### [NEW] `database/migrations/2026_08_11_xxxx_create_conversations_table.php`
- `tenant_id` (FK, BelongsToTenant)
- `customer_id` (FK)
- `status` (Enum: active, resolved, handed_over)

#### [NEW] `database/migrations/2026_08_11_xxxx_create_messages_table.php`
- `conversation_id` (FK)
- `role` (Enum: user, assistant, system, tool)
- `content` (Text)
- `tool_calls` (JSON, Nullable)

### 2. Capa Asíncrona: Rehidratación del Contexto

#### [MODIFY] [ProcessWhatsAppMessageJob.php](file:///c:/Users/luis%20jorge%20fiestas/Desktop/aiva-laravel-host/app/Jobs/ProcessWhatsAppMessageJob.php)
El cascarón vacío del M3.8 se llenará con lógica de rehidratación:
1. `ExecutionContext::setTenant(Tenant::find($this->tenantId))`
2. Extraer teléfono del payload (`messages[0]['from']`) y el nombre (`contacts[0]['profile']['name']`).
3. Buscar/Crear `Customer` y `Conversation`.
4. Registrar el mensaje entrante (`role: user`).
5. Invocar al `AIGuardianService`.

### 3. El Motor de IA: AIGuardianService 🤖

#### [NEW] `app/Modules/AI/Services/AIGuardianService.php`
El controlador maestro que orquesta la conversación:
- Construye el **System Prompt** inyectando dinámicamente información clave del negocio (Nombre, Políticas).
- Recupera los últimos N mensajes de la base de datos y los formatea para el LLM.
- Declara el esquema estricto de JSON (Tools) conectando el cerebro con el `ProductCatalogService` y `InventoryEngine` (Módulo 2).

#### [NEW] `app/Modules/AI/Tools/CatalogTool.php` (y similares)
Clases que definen la firma (Schema JSON) que leerá el LLM y que encapsulan la ejecución real hacia los Servicios del Core. Ejemplos previstos:
- `search_products(query)` -> Consulta la vitrina.
- `check_availability(sku)` -> Consulta stock.
- `reserve_item(sku, quantity)` -> Invoca a `InventoryEngine`.

### 4. Capa de Salida: Adapter de WhatsApp

#### [NEW] `app/Modules/WhatsApp/Services/WhatsAppOutboundService.php`
El servicio encargado de tomar la respuesta en texto plano del LLM y enviarla de regreso a la API de WhatsApp Cloud utilizando el `access_token` desencriptado de la `WhatsAppConnection`.

## Verification Plan

Diseñaremos una suite TDD robusta (`AIGuardianTest`) que:
- Simule la llegada de un webhook JSON válido con un mensaje real del cliente (Ej: "Quiero dos donas clásicas").
- Intercepte/Mokee el cliente HTTP de OpenAI para simular que el LLM responde solicitando llamar a la función `reserve_item`.
- Verifique que la herramienta ejecuta el código, reduce el stock en BD (sin romper el Freeze) y devuelve éxito.
- Compruebe que se genera el request HTTP saliente hacia Meta con la confirmación para el cliente.
