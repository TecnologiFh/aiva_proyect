# Walkthrough - Sprint 9: AI CRM & Customer Brain (Cerebro del Cliente en Redis)

Hemos completado al 100% la infraestructura y codificación del **Sprint 9 (AI CRM & Customer Brain)** de la plataforma SaaS **AIVA**. Este módulo evoluciona la gestión de clientes desde un CRM tradicional hacia un **Cerebro del Cliente en Redis (`CustomerBrainCompiler`)** que permite al Motor de IA (Sprint 4) atender a través de WhatsApp (Sprint 5) y canales omnicanal con hiper-personalización e historial comercial instantáneo a **`0ms` de latencia en la base de datos**.

---

## 1. Componentes Construidos en el Sprint 9

### 1.1 Omnicanalidad & Identidad (`customers` & `customer_identities`)
- **`ChannelType` Enum**: `WHATSAPP`, `INSTAGRAM`, `MESSENGER`, `TELEGRAM`, `WEB`.
- **`CustomerStage` Enum**: `NEW`, `ACTIVE`, `LOYAL`, `VIP`, `INACTIVE`, `LOST`.
- Permite unificar perfiles de clientes sin importar el canal por el cual se comuniquen (`+51999888777`, `@luis`).

### 1.2 Perfil Comercial Inteligente & Score (`customer_profiles`)
- Métricas avanzadas: `total_orders_count`, `total_spent`, `average_ticket`, `clv`, `cancellation_rate`, `orders_30d`, `orders_90d`, `customer_score` (Score dinámico de 0 a 100).
- Preferencias aprendidas: `favorite_category`, `preferred_payment`, `preferred_delivery`, `ai_summary`.

### 1.3 Innovación Central: Customer Brain Compiler (`CustomerBrainCompiler`)
- Compila y almacena en Redis el snapshot inteligente del cliente:
  `tenant:{tenant_id}:customer:{customer_id}:brain`
- Inyectado junto con el **Business Brain** (Sprint 3) por el `PromptContextBuilder` para ofrecer atención hiper-personalizada por WhatsApp (*"Hola Luis, la última vez pediste Pizza Familiar sin cebolla. ¿Deseas repetir ese pedido?"*) a **costo $0 de latencia de BD**.

### 1.4 Línea de Tiempo & Geolocalización (`customer_timeline` & `customer_addresses`)
- `customer_timeline`: Auditoría cronológica de hitos (`MESSAGE`, `ORDER_CREATED`, `PAYMENT_APPROVED`, `CLAIM`).
- `customer_addresses`: Libreta de direcciones con geolocalización (`latitude`, `longitude`, `delivery_zone`).

### 1.5 System Tags vs User Tags & Marketing
- `TagType` Enum (`SYSTEM`, `USER`): `SYSTEM` tags calculados automáticamente (`VIP`, `RISK_OF_CHURN`, `HIGH_SPENDER`) vs `USER` tags manuales.
- `customer_marketing_preferences`: Preferencias de canal y recepción de ofertas.

### 1.6 Controladores, Rutas API & RBAC
- **`CustomerController`**:
  - `GET /api/v1/customers`: Búsqueda y segmentación inteligente.
  - `GET /api/v1/customers/{uuid}`: Perfil 360° con timeline, memorias y Customer Brain.
  - `POST /api/v1/customers/{uuid}/addresses`: Direcciones frecuentes con geolocalización.
  - `POST /api/v1/customers/{uuid}/tags`: Asignación de etiquetas.
- Protegidos por `RequirePermission` middleware (`crm:view`, `crm:edit`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/CustomerBrainCompilerTest.php`: Verifica la DTO del Customer Brain compilado para la IA.
- `tests/Feature/CustomerMetricsAndScoreTest.php`: Verifica el cálculo del Score (0-100) y atributos de métricas avanzadas.
- `tests/Feature/CustomerTimelineTest.php`: Auditoría de la línea de tiempo del cliente (`customer_timeline`).

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 10)

El **Sprint 9 (AI CRM & Customer Brain)** ha quedado 100% construido y probado. AIVA cuenta con perfiles de cliente omnicanal, inteligencia en Redis y segmentación avanzada.

Estamos listos para el **Sprint 10: Analytics, Metrics & Business Intelligence** (Dashboards ejecutivos de ventas, métricas de desempeño de la IA, embudo de conversión WhatsApp, tasa de abandono de pedidos e informes ejecutivos).
