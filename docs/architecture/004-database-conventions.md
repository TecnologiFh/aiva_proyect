# 004 - Convenciones de Base de Datos (Database Conventions)

## Reglas Obligatorias de Esquema SQL en MySQL 8

1. **Multi-Tenancy por Defecto**:
   - Toda tabla perteneciente a un dominio empresarial DEBE incluir la columna `tenant_id` (`BIGINT UNSIGNED NOT NULL`).
   - Debe crearse un índice compuesto `INDEX (tenant_id, ...)` en las columnas frecuentemente consultadas.

2. **Identificadores (IDs Internos vs. Públicos)**:
   - Primary Key interna: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
   - Identificador público: `uuid BINARY(16) NOT NULL UNIQUE`.
   - Las Foreign Keys entre tablas SIEMPRE referencian al `id` (`BIGINT UNSIGNED`), JAMÁS al `uuid`.
   - El `uuid` se reserva exclusivamente para consumo de la API REST externa.

3. **Soft Deletes Obligatorio**:
   - Todas las tablas de negocio incluyen `deleted_at TIMESTAMP NULL` (trait `SoftDeletes` de Laravel).
   - Nunca borrar registros físicamente de la base de datos.

4. **Auditoría de Registro**:
   - Incluir `created_by BIGINT UNSIGNED NULL` y `updated_by BIGINT UNSIGNED NULL` en todas las entidades primarias.

5. **Estados Mediante Enums**:
   - No usar `VARCHAR` libre para estados. Utilizar Enums respaldados por strings en PHP 8.1+ (`TenantStatus`, `UserStatus`).

6. **Timestamps Portables**:
   - Usar `$table->timestamps()` de Laravel sin forzar sentencias SQL propietarias como `CURRENT_TIMESTAMP` en migraciones.
