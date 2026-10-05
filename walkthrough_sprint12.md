# Walkthrough - Sprint 12: Campaign Manager (Gestor de Campañas Segmentadas por WhatsApp)

Hemos completado al 100% la infraestructura y codificación del **Sprint 12 (Campaign Manager)** de la plataforma SaaS **AIVA**. Este módulo conecta la inteligencia del **AI CRM & Customer Brain** (Sprint 9) y las variables dinámicas de **VariableResolver** (Sprint 11) con el motor de mensajería comercial de WhatsApp (Sprint 5), procesando envíos masivos en lotes asíncronos mediante **Queue Jobs (`ProcessCampaignBatchJob`)**.

---

## 1. Componentes Construidos en el Sprint 12

### 1.1 Segmentación Inteligente con el AI CRM (`CampaignTargetSegment`)
- Permite filtrar clientes según su comportamiento comercial:
  - `VIP_CUSTOMERS`: Clientes con `customer_score >= 80` o en etapa `VIP`.
  - `INACTIVE_30D`: Clientes con `> 30 días` sin comprar (Re-engagement / Recuperación).
  - `HIGH_SPENDERS`: Clientes con ticket promedio elevado.
  - `CUSTOM_TAGS`: Clientes con etiquetas personalizadas.

### 1.2 Procesamiento Asíncrono por Lotes (`ProcessCampaignBatchJob`)
- **Queue Job de Producción**: Procesa los disparos masivos en lotes de 50 en 50 en segundo plano.
- **Protección de API**: Evita saturar el servidor y respeta los límites de la API de Meta Cloud API.

### 1.3 Personalización Dinámica por Destinatario
- Inyecta variables dinámicas por cada cliente (`{{customer.name}}`, `{{customer.score}}`, `{{customer.favorite_category}}`) usando el `VariableResolver` (Sprint 11).

### 1.4 Auditoría & Analítica en Tiempo Real (`campaign_recipients`)
- Registra el estado individual de cada mensaje enviado (`PENDING`, `SENT`, `DELIVERED`, `FAILED`).
- Calcula el porcentaje de entrega (`delivery_rate_percentage`) y métricas de efectividad.

### 1.5 Controladores, Rutas API & RBAC
- **`CampaignController`**:
  - `GET /api/v1/campaigns`: Listado de campañas del tenant.
  - `POST /api/v1/campaigns`: Creación de borrador o campaña programada.
  - `POST /api/v1/campaigns/{uuid}/launch`: Disparo masivo de campaña en colas asíncronas.
  - `GET /api/v1/campaigns/{uuid}/analytics`: Analítica de tasa de entrega y efectividad.
- Protegidos por `RequirePermission` middleware (`crm:view`, `crm:edit`).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/CampaignSegmentationTest.php`: Verifica los criterios de segmentación comercial del CRM.
- `tests/Feature/CampaignJobBatchExecutionTest.php`: Instanciación y flujo del Queue Job `ProcessCampaignBatchJob`.
- `tests/Feature/CampaignAnalyticsTest.php`: Verifica las métricas y atributos de entrega de campañas.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 13)

El **Sprint 12 (Campaign Manager)** ha quedado 100% construido y probado. AIVA posee la capacidad de ejecutar campañas masivas altamente rentables por WhatsApp.

Estamos listos para el **Sprint 13: Human Console** (Consola multi-operador estilo Zendesk para atención híbrida con asignación de agentes, transferencias y respuestas rápidas).
