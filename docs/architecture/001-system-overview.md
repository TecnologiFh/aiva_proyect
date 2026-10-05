# 001 - Visión General del Sistema (System Overview)

## Arquitectura General de AIVA

AIVA es una plataforma SaaS multi-empresa dividida en capas desacopladas que utilizan la inteligencia artificial como una capacidad asistencial integrada a los módulos del negocio.

```mermaid
graph TD
    Client[Cliente Web / WhatsApp / Integraciones] -->|HTTP Request| API[API Layer - Laravel 11]
    
    subgraph Shared Infrastructure
        Middleware[CorrelationId & ResolveTenantContext Middleware]
        ResponseHandler[ApiResponse Formatter]
    end
    
    API --> Middleware
    
    subgraph Modular Architecture
        TenantMod[Tenant Module]
        IdentityMod[Identity Module]
        BusinessMod[Business Brain Module]
        ConversationMod[Conversation Engine Module]
        AIMod[AI Engine Module]
        OrderMod[Order Engine Module]
        SystemMod[System Diagnostic Module]
    end
    
    Middleware --> TenantMod
    Middleware --> IdentityMod
    Middleware --> BusinessMod
    Middleware --> ConversationMod
    Middleware --> AIMod
    Middleware --> OrderMod
    Middleware --> SystemMod
    
    subgraph Storage Layer
        MySQL[(MySQL 8 - Shared DB Multi-tenant)]
        RedisCache[(Redis - Cache Pool)]
        RedisQueue[(Redis - Queue Pool)]
        RedisLock[(Redis - Atomic Locks)]
    end
    
    TenantMod --> MySQL
    IdentityMod --> MySQL
    BusinessMod --> MySQL
    ConversationMod --> MySQL
    OrderMod --> MySQL
    
    TenantMod --> RedisCache
    OrderMod --> RedisLock
    AIMod --> RedisQueue
```

## Capas del Proyecto
1. **Core (`app/Core/`)**: Contratos, DTOs, Enums, EventBus, Excepciones, Traits (`BelongsToTenant`, `HasBinaryUuid`, `Auditable`).
2. **Application (`app/Application/` & `app/Modules/*/Application/`)**: Casos de uso aislados (Actions).
3. **Modules (`app/Modules/`)**: Funcionalidades de negocio agrupadas en dominios autónomos.
4. **Shared (`app/Shared/`)**: Middlewares de infraestructura y controladores globales.
