# ADR-008: Registro de la Hoja de Ruta de Arquitectura (Architecture Roadmap v2.0)

## Estatus
Aprobado (Ampliado a Sprints 1 al 15)

## Contexto
AIVA es una plataforma SaaS multi-tenant que conecta Inteligencia Artificial, canales de mensajería (WhatsApp) y gestión comercial en tiempo real. Para garantizar una evolución Lean sólida y escalable hacia una plataforma SaaS empresarial de clase mundial, se extiende la Hoja de Ruta de Módulos hasta el Sprint 15.

## Decisión
Registrar oficialmente la Hoja de Ruta Ampliada de Módulos de AIVA:

- **Sprint 1**: Infraestructura Multi-tenant, Autenticación Base & Governance (`docs/`).
- **Sprint 2**: Identity Engine & Control de Acceso RBAC Granular.
- **Sprint 3**: Business Brain MVP & Compilación de Conocimiento en Redis (`BusinessKnowledgeCompiler`).
- **Sprint 4**: AI Engine MVP, AI Gateway Multi-Proveedor & Salida Estructurada.
- **Sprint 5**: WhatsApp Connector, Meta Cloud API Webhooks & Orquestación Asíncrona.
- **Sprint 6**: Order Engine MVP, `order_version`, timestamps operacionales (`confirmed_at`, `preparing_at`, `ready_at`, `delivered_at`, `cancelled_at`).
- **Sprint 7**: Payments Engine & Pasarelas de Pago (Yape/Plin/Online), desacoplado por eventos (`PaymentApproved`).
- **Sprint 8**: Inventory & Stock Management (Descuento físico en `PREPARING`, control concurrente `available_stock = current_stock - reserved_stock`).
- **Sprint 9**: AI CRM & Customer Brain en Redis (Score 0-100, omnicanalidad, productos preferidos compilados).
- **Sprint 10**: Analytics, Metrics & Business Intelligence (Dashboard ROI de IA, comparación de tiempos de respuesta IA vs Humano, Embudo WhatsApp).
- **Sprint 11**: Automation Engine (Motor de reglas y disparadores automáticos estilo Zapier).
- **Sprint 12**: Campaign Manager (Gestor de campañas de mensajería segmentadas).
- **Sprint 13**: Human Console (Consola multi-operador para atención híbrida estilo Zendesk).
- **Sprint 14**: Loyalty Engine (Programa de puntos, cashback, gift cards y referidos).
- **Sprint 15**: Predictive AI (Inteligencia predictiva de stock, sugerencias de venta cruzada upsell/cross-sell).

## Consecuencias
### Positivas
- Claridad estratégica y técnica absoluta para evolucionar de un chatbot a una plataforma empresarial de automatización e inteligencia comercial.
- Desacoplamiento estricto respetando el principio Open/Closed.
