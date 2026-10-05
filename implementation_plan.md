# Arquitectura e Plan de Implementación v3.2 - Sprint 1: Core SaaS & Multi-Tenant Lean MVP

## Resumen Ejecutivo

Esta versión **v3.2** consolida la estructura completa de gobernanza, documentación viva de la arquitectura (`docs/architecture/`) y ubica los endpoints del sistema (Health, Metrics, Status) dentro de su módulo natural `app/Modules/System/`.

---

## 1. Gobernanza y Documentación Viva (`docs/`)

```text
docs/
├── adr/                                # Architecture Decision Records (Inmutables)
│   ├── ADR-001-shared-database-multi-tenancy.md
│   ├── ADR-002-laravel-framework.md
│   ├── ADR-003-binary-uuid.md
│   └── ADR-004-action-pattern-clean-architecture.md
│
├── architecture/                       # Documentos Vivos de Arquitectura
│   ├── 001-system-overview.md           # Diagrama general de la plataforma
│   ├── 002-request-flow.md              # Flujo detallado de la petición HTTP
│   ├── 003-module-interaction.md        # Matriz de comunicación inter-módulos
│   ├── 004-database-conventions.md      # Convenciones estricta de base de datos
│   └── 005-event-flow.md                # Catálogo y flujo de Domain Events
│
├── ai/                                 # Reglas de desarrollo para asistentes IA
│   └── CODING_RULES.md
│
└── engineering/                        # Filosofía de desarrollo
    └── ENGINEERING_PRINCIPLES.md
```

---

## 2. Estructura de Módulos Actualizada (`app/Modules/System`)

Los endpoints de diagnóstico y estado del sistema residirán en `app/Modules/System/`:

```text
AIVA/
├── app/
│   ├── Core/                           # Abstracciones globales (Contracts, DTOs, Enums, EventBus, Traits)
│   ├── Application/                    # Capa de Aplicación
│   ├── Modules/                        # Módulos del Sistema
│   │   ├── System/                     # Módulo de Estado, Salud y Diagnóstico
│   │   │   ├── Controllers/
│   │   │   │   └── HealthCheckController.php   # GET /health
│   │   │   └── Routes/api.php
│   │   │
│   │   ├── Tenant/                     # Módulo Multi-Tenant
│   │   │   ├── Application/            # CreateTenantAction, CreateTenantInput, CreateTenantResult
│   │   │   ├── Controllers/            # TenantController
│   │   │   ├── Database/Migrations/    # tenants, tenant_settings
│   │   │   ├── Models/                 # Tenant, TenantSetting
│   │   │   ├── Repositories/           # TenantRepository
│   │   │   ├── Requests/               # CreateTenantRequest
│   │   │   └── Routes/api.php
│   │   │
│   │   └── Identity/                   # Módulo de Usuarios y Seguridad
│   │       ├── Application/            # RegisterUserAction, AuthenticateUserAction
│   │       ├── Controllers/            # AuthController
│   │       ├── Database/Migrations/    # users, roles, permissions, role_user, audit_logs
│   │       ├── Models/                 # User, Role, Permission, AuditLog
│   │       ├── Repositories/           # UserRepository, AuditRepository
│   │       ├── Requests/               # RegisterUserRequest, LoginRequest
│   │       └── Routes/api.php
│   │
│   ├── Shared/                         # Responses (ApiResponse) y Middlewares (CorrelationId, ResolveTenant)
│   └── Providers/
│       └── ModuleDiscoveryServiceProvider.php
│
├── docs/                               # Documentación Completa (ADRs, Architecture, AI, Engineering)
└── config/
```

---

## 3. Plan de Ejecución Inmediato

### Fase A: Creación de la Suite de Documentación (`docs/`)
1. **ADRs** (`ADR-001`, `ADR-002`, `ADR-003`, `ADR-004`).
2. **Architecture Live Docs** (`001-system-overview.md`, `002-request-flow.md`, `003-module-interaction.md`, `004-database-conventions.md`, `005-event-flow.md`).
3. **AI Rules** (`docs/ai/CODING_RULES.md`).
4. **Engineering Principles** (`docs/engineering/ENGINEERING_PRINCIPLES.md`).

### Fase B: Scaffolding de Laravel 11 y Módulos Core
1. Instalación de proyecto base Laravel 11.
2. Configuración de Sanctum y Redis.
3. Creación de `app/Core/`, `app/Application/`, `app/Modules/`, `app/Shared/`.
4. Creación e integración de `app/Modules/System/` (`GET /health`).

### Fase C: Implementación de Módulos Tenant & Identity
1. Migraciones y Modelos.
2. Contratos y Repositorios.
3. Actions (Use Cases) y Controladores.
4. Pruebas automatizadas de Aislamiento y Autenticación.
