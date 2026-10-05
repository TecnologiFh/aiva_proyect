# Arquitectura e Plan de Implementación v2.2 - Sprint 2: Usuarios, Roles, Permisos y Seguridad (Identity Engine)

## Resumen Ejecutivo

Esta versión **v2.2** finaliza la especificación del **Sprint 2**, incorporando el flujo seguro de **Transferencia de Propiedad** (`TransferOwnershipAction`), la separación física de los usuarios del equipo interno de AIVA en `platform_users`, la política `RoleAssignmentPolicy`, la precarga de caché de permisos en el login (Cache Warm-up), la matriz RBAC centralizada en `config/rbac.php`, el evento de dominio `UserLoggedIn` y el cumplimiento estricto de timestamps portables manejados exclusivamente por Laravel (`$table->timestamps()`).

---

## 1. Nuevos Componentes de Producto & Seguridad (v2.2)

### 1.1 Caso de Uso `TransferOwnershipAction`
Ubicación: `app/Modules/Identity/Application/TransferOwnership/`
- Permite al `OWNER` actual transferir la propiedad del negocio a otro usuario activo del tenant.
- Operación atómica en transacción:
  1. Valida mediante `OwnerGuard` que el usuario receptor pertenezca a la misma empresa y esté activo.
  2. Asigna el rol `OWNER` al nuevo usuario.
  3. Reasigna al `OWNER` anterior el rol `ADMIN` (o según se especifique).
  4. Incrementa la versión de caché de permisos del tenant.
  5. Dispara el Domain Event `OwnershipTransferred` y registra auditoría.

### 1.2 Separación de Usuarios de Plataforma (`platform_users`)
Los administradores y personal de soporte de la plataforma AIVA NO se mezclan en la tabla `users` de los tenants. Se crean aislados en la tabla `platform_users`:
- Permite suplantación (impersonation) segura y auditada sin contaminar los datos del cliente.
- Autenticación por Guard Sanctum independiente (`sanctum:platform`).

### 1.3 `RoleAssignmentPolicy`
Ubicación: `app/Core/Authorization/Policies/RoleAssignmentPolicy.php`
- Impide que un usuario con rol `ADMIN` pueda promover a otros o autopromoverse a `OWNER`.
- Impide la degradación no autorizada de jerarquías.

### 1.4 Centralización de Matriz RBAC en `config/rbac.php`
```php
use App\Core\Enums\RoleType;
use App\Core\Enums\Permission\IdentityPermission;
use App\Core\Enums\Permission\BusinessPermission;

return [
    'roles' => [
        RoleType::OWNER->value => [
            IdentityPermission::MANAGE_USERS->value,
            IdentityPermission::ASSIGN_ROLES->value,
            BusinessPermission::MANAGE_SETTINGS->value,
            // ...
        ],
        RoleType::ADMIN->value => [
            IdentityPermission::MANAGE_USERS->value,
            BusinessPermission::MANAGE_PRODUCTS->value,
            // ...
        ],
    ],
];
```
El `IdentityInitializationSeeder` lee directamente de esta configuración y los Enums para sincronizar la base de datos.

### 1.5 Cache Warm-up en el Login
En `AuthenticateUserAction`:
- Inmediatamente después de verificar las credenciales y generar el token Sanctum, invoca `$permissionService->warmupCache($user)`.
- El primer request del cliente tras iniciar sesión obtiene un **100% de hit en caché Redis**.

### 1.6 Estandarización Estricta de Timestamps Portables
Todas las migraciones utilizan únicamente `$table->timestamps()` y `$table->softDeletes()` de Eloquent. Se prohíbe cualquier sentencia SQL directa con `DEFAULT CURRENT_TIMESTAMP` u `ON UPDATE` para mantener 100% la portabilidad y coherencia.

---

## 2. Esquema de Base de Datos (Sprint 2 v2.2)

### 2.1 Tabla `platform_users` (Soporte e Ingenieros AIVA)
```sql
CREATE TABLE platform_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'SUPPORT', -- SUPERADMIN, SUPPORT, BILLING
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Tabla Pivote Explícita `role_permission`
```sql
CREATE TABLE role_permission (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Plan de Ejecución Inmediato del Sprint 2

1. **Paso 1: Archivos de Configuración & Enums**:
   - Crear `config/rbac.php`.
   - Crear Enums de Permisos por Dominio en `app/Core/Enums/Permission/`.
2. **Paso 2: Migraciones & Base de Datos**:
   - Migración para `last_login_at`/`last_login_ip` en `users`.
   - Migración de `platform_users`.
   - Migración de `role_permission`.
   - Migración de `user_role_history`.
3. **Paso 3: Servicios & Guards**:
   - `PermissionService` (con Warm-up y caché por versión).
   - `OwnerGuard` (reglas de protección del propietario).
4. **Paso 4: Domain Events**:
   - `EmployeeCreated`, `UserRoleChanged`, `OwnershipTransferred`, `UserLoggedIn`, `UserBlocked`, `UserActivated`.
5. **Paso 5: Seeders**:
   - `IdentityInitializationSeeder` (lee de `config/rbac.php` y sincroniza la BD).
6. **Paso 6: Casos de Uso (Actions)**:
   - `CreateEmployeeAction`
   - `UpdateUserStatusAction`
   - `AssignUserRoleAction`
   - `TransferOwnershipAction`
   - `ListUsersAction`
7. **Paso 7: Middlewares, Controladores y Rutas**:
   - `RequirePermission` middleware (`permission:any:...` / `permission:all:...`).
   - `UserController` y `RoleController`.
   - Actualización de `AuthenticateUserAction` (emisión de evento `UserLoggedIn` y Cache Warm-up).
8. **Paso 8: Suite de Pruebas Automatizadas (Security Suite)**:
   - Tests de Transferencia de Propiedad, Cache Warm-up, Protección OWNER, y aislación de `platform_users`.
