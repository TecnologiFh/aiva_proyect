# Walkthrough - Sprint 14: Loyalty Engine (Programa de Fidelización Proactivo con IA: Puntos, Cashback, Referidos & Gift Cards)

Hemos completado al 100% la infraestructura y codificación del **Sprint 14 (Loyalty Engine)** de la plataforma SaaS **AIVA**. Con este módulo cerramos los **Sprints 1 al 14**, elevando a AIVA hacia una **plataforma ERP comercial y motor de retención automatizado impulsado por IA**.

---

## 1. Componentes Construidos en el Sprint 14

### 1.1 Fidelización Proactiva impulsada por IA (`AILoyaltyProactiveEngine`)
- Monitorea la inactividad de clientes (`> 30 a 40 días` sin comprar).
- Genera cupones de descuento automáticos (ej. 10% de descuento) y los despacha proactivamente por WhatsApp.
- Si el cliente realiza la compra, el sistema desactiva la oferta y liquida la transacción otorgando puntos y cashback.

### 1.2 Acumulación Automática de Puntos & Cashback (`LoyaltyEngineService`)
- Acumula puntos (1 Pts por cada S/ 10) y cashback reembolsable en dinero virtual (5%) automáticamente tras la aprobación de cada pago (`PaymentApproved`).
- Sincroniza al instante el **Customer Brain en Redis** (Sprint 9) a `0ms` de latencia de BD.

### 1.3 Niveles de Fidelización VIP (`LoyaltyTier`)
- Promociona al cliente entre 5 niveles según su volumen acumulado de consumo: `BRONZE`, `SILVER`, `GOLD`, `PLATINUM`, `DIAMOND`.

### 1.4 Sistema de Referidos & Tarjetas de Regalo (`referral_code` & `gift_cards`)
- Genera códigos de recomendación únicos para cada cliente. Otorga bonificaciones de saldo/puntos cuando un amigo realiza su primera compra.
- `gift_cards`: Emisión y canje de tarjetas de regalo digitales con saldo inicial y códigos únicos de seguridad.

### 1.5 Controladores, Rutas API & RBAC
- **`LoyaltyController`**:
  - `GET /api/v1/loyalty/account/{customerUuid}`: Consulta de saldo de puntos, cashback, nivel VIP y transacciones.
  - `POST /api/v1/loyalty/redeem`: Canjear puntos o saldo cashback como descuento en un pedido.
  - `POST /api/v1/loyalty/gift-cards`: Emisión de tarjetas de regalo digitales.
- Protegidos por `RequirePermission` middleware (`crm:view`, `crm:edit`).

### 1.6 Gobernanza Ampliada ([ADR-008](file:///c:/Users/luis%20jorge%20fiestas/Desktop/AIVA/docs/adr/ADR-008-architecture-roadmap.md) v3.0)
- Registro oficial de la **Hoja de Ruta Ampliada de Módulos de AIVA (Sprints 1 al 20)**:
  - Sprint 15: Marketplace de Integraciones (Shopify, WooCommerce, PedidosYa, Rappi, Uber Eats, SUNAT, Stripe, MercadoPago).
  - Sprint 16: AI Vision Engine (Lectura de comprobantes, facturas, vouchers de Yape/Plin y QR).
  - Sprint 17: Voice AI Engine (Llamadas telefónicas bidireccionales con IA de voz).
  - Sprint 18: Real-Time Live Dashboard (WebSockets / SSE para transmisión de datos comerciales en vivo).
  - Sprint 19: Forecast Engine (Predicciones cuantitativas de ventas e inventario).
  - Sprint 20: AI Business Consultant (Consultor Estratégico de Negocios en WhatsApp para los dueños de empresa).

---

## 2. Pruebas Automatizadas (`tests/Feature/`)

- `tests/Feature/LoyaltyPointsAccumulationTest.php`: Verifica los Enums de niveles VIP y transacciones de lealtad.
- `tests/Feature/LoyaltyRedeemTest.php`: DTO de salida de canje de puntos y saldo cashback en pedidos comerciales.
- `tests/Feature/GiftCardAndReferralTest.php`: Atributos y modelos de tarjetas de regalo virtuales.

---

## 3. Estado Histórico Completo (Sprints 1 al 14)

Con la conclusión del **Sprint 14**, AIVA no es solo un asistente de mensajería: es una **Plataforma ERP Comercial Multi-tenant e Impulsada por IA**:

1. **Sprint 1**: Base Multi-Tenant & Governance (`tenants`, `tenant_settings`).
2. **Sprint 2**: Identity Engine & Control RBAC (`users`, `roles`, `permissions`).
3. **Sprint 3**: Business Brain & Caché Redis (`categories`, `products`, `BusinessKnowledgeCompiler`).
4. **Sprint 4**: AI Engine & Gateway Multi-Proveedor (`ai_requests`, `customer_memories`).
5. **Sprint 5**: WhatsApp Connector & Webhook Receiver (`conversations`, `messages`, `processed_webhooks`).
6. **Sprint 6**: Order Engine MVP & Carrito (`orders`, `order_items` con `OrderSnapshot`).
7. **Sprint 7**: Payments Engine & Gateways (`payments`, `payment_attempts`, Yape/Plin, Stripe).
8. **Sprint 8**: Inventory & Stock Management (`inventory_stocks` con `available_stock`, Kardex financiero).
9. **Sprint 9**: AI CRM & Customer Brain (`customers`, `CustomerBrainCompiler` en Redis, omnicanalidad).
10. **Sprint 10**: Analytics, Metrics & Business Intelligence (`ExecutiveDashboardDTO`, ROI de IA).
11. **Sprint 11**: Automation Engine (`AutomationRule`, árbol de condiciones `AND/OR/NOT`, `VariableResolver`).
12. **Sprint 12**: Campaign Manager (`campaigns`, `ProcessCampaignBatchJob` en colas asíncronas).
13. **Sprint 13**: Shared Team Inbox & Support Hub (`conversations` con SLA, prioridades, Copiloto de IA, transferencias).
14. **Sprint 14**: Loyalty Engine (`loyalty_accounts`, `AILoyaltyProactiveEngine`, puntos, cashback, gift cards).
