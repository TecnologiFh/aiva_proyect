# 005 - Catálogo y Flujo de Eventos (Event Flow)

## Catálogo de Domain Events del Sistema

Los Domain Events representan hechos pasados e inmutables del negocio.

```text
Domain Event          Listeners / Reacciones
─────────────         ───────────────────────────────────────────────────
TenantCreated    ───► 1. Asigna configuraciones por defecto (TenantSetting)
                      2. Crea usuario inicial con rol OWNER
                      3. Registra Log de Auditoría (AuditLog)
                      4. Invalida / Inicializa caché del Tenant

UserRegistered   ───► 1. Asigna rol inicial (RoleUser)
                      2. Envía correo de bienvenida / verificación
                      3. Registra Log de Auditoría (AuditLog)

OrderConfirmed   ───► 1. Notifica al panel de cocina / negocio
                      2. Actualiza estadísticas comerciales
                      3. Acumula puntos en motor de fidelización
                      4. Dispara mensaje de confirmación por WhatsApp
```
