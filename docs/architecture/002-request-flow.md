# 002 - Flujo de la Petición HTTP (Request Flow)

## Secuencia de Procesamiento de una Solicitud

```text
1. Cliente envía Petición HTTP
   │
2. CorrelationIdMiddleware
   ├── Asigna o extrae X-Correlation-ID (UUID v4)
   └── Lo inyecta en el contexto de Monolog y en las cabeceras de respuesta
   │
3. ResolveTenantContext Middleware
   ├── Jerarquía de resolución:
   │   1. Token Sanctum (usuario autenticado)
   │   2. Subdominio / Dominio HTTP (ej. donutsfs.aiva.pe)
   │   3. Cabecera HTTP (X-Tenant-Slug / X-Tenant-ID)
   │   4. API Key (X-API-Key)
   └── Setea el TenantContext Singleton para la petición actual
   │
4. Route & Controller (ej. TenantController / AuthController)
   ├── Valida la entrada HTTP vía FormRequest
   └── Invoca el Action correspondiente de la Capa Application
   │
5. Action (Caso de Uso de la Capa Application)
   ├── Ejecuta validaciones de reglas de negocio
   ├── Interactúa con Repositorios mediante Interfaces del Core
   ├── Despacha Domain Events a través de EventBusInterface
   └── Registra auditoría a través de AuditService
   │
6. Repository & Eloquent Model
   ├── Aplica automático Global Scope `BelongsToTenant`
   └── Convierte transparentemente UUID Binary(16) a String
   │
7. Response Formatter (ApiResponse)
   └── Retorna formato JSON uniforme:
       {
         "success": true,
         "data": { ... },
         "message": "Operación realizada con éxito"
       }
```
