# ADR-001: Multi-Tenancy mediante Shared Database + Shared Schema

## Estatus
Aprobado

## Contexto
AIVA es una plataforma SaaS orientada a atender a miles de pequeños y medianos negocios. Necesitamos aislar la información empresarial garantizando máxima eficiencia en costos de infraestructura, velocidad en migraciones y facilidad de mantenimiento.

## Decisión
Adoptamos el modelo de **Shared Database + Shared Schema** en MySQL 8.
Cada tabla perteneciente al dominio empresarial incluirá obligatoriamente la columna `tenant_id` (`BIGINT UNSIGNED NOT NULL`).

El aislamiento a nivel de código se logra mediante un Global Scope en Eloquent (`BelongsToTenant` trait), el cual inyecta automáticamente la condición `WHERE tenant_id = ?` en todas las consultas del modelo.

## Consecuencias
### Positivas
- Reducción drástica del costo de infraestructura (una sola base de datos gestionada).
- Migraciones instantáneas para todas las empresas en un solo paso (`php artisan migrate`).
- Facilidad para realizar analítica agregada y backups globales.

### Negativas
- Riesgo de fuga de datos si una consulta omite el Global Scope.
- Mitigación: Uso estricto del trait `BelongsToTenant` y pruebas de integración automatizadas de aislamiento entre empresas (`TenantIsolationTest`).
