# 003 - Interacción entre Módulos (Module Interaction)

## Matriz de Comunicación Inter-Módulos

Para preservar la modularidad y evitar dependencias circulares entre dominios, los módulos se comunican exclusivamente a través de:
1. **Domain Events** (Asíncronos / EventBus).
2. **Core Contracts / Interfaces** (Inyección de dependencias).

```text
Identity ───────► Tenant          (Asigna usuarios y valida permisos por empresa)
  │                 │
  ▼                 ▼
Business ───────► Conversation    (Productos y configuración nutren las respuestas de IA)
  │                 │
  ▼                 ▼
Order  ─────────► AI Engine       (Intenciones detectadas por IA crean carritos y pedidos)
  │                 │
  ▼                 ▼
Automation ─────► WhatsApp        (Campañas y automatizaciones envían mensajes al conector)
  │
  ▼
Billing           (Monitoreo de límites de uso por suscripción)
```

## Reglas de Interacción
- Ningún módulo puede realizar consultas SQL directas a tablas de otro módulo.
- Si el Módulo Order necesita datos del Cliente, debe consultar `CustomerRepositoryInterface` o escuchar el evento `CustomerRegistered`.
- La IA nunca ejecuta inserciones directas en base de datos. Pasa por el `OrderEngine` o `ConversationEngine`.
