# Walkthrough - Sprint 2: Usuarios, Roles, Permisos y Seguridad (Identity Engine & Authorization)

Hemos completado exitosamente la construcción e infraestructura completa del **Sprint 2** de la plataforma SaaS **AIVA**, implementando un motor de **Identidad, Seguridad y RBAC Multi-Tenant** de máxima calidad empresarial bajo los patrones **Clean Architecture** y **DDD**.

---

## 1. Componentes Construidos en el Sprint 2

### 1.1 Configuración Declarativa & Enums de Permisos (`Core/Enums/Permission/`)
- **`config/rbac.php`**: Matriz declarativa centralizada que mapea los Roles (`OWNER`, `ADMIN`, `EMPLOYEE`, `KITCHEN`, `SUPPORT`) con sus permisos.
- **Enums de Permisos por Dominio**:
  - `IdentityPermission` (`identity:manage_users`, `identity:assign_roles`, `identity:view_users`)
  - `BusinessPermission` (`tenant:manage_settings`, `business:manage_products`, `business:view_products`)
  - `OrderPermission` (`order:create`, `order:update_status`, `order:cancel`)
  - `ConversationPermission` (`conversation:read`, `conversation:reply`)
  - `ReportPermission` (`reports:view`)

### 1.2 Base de Datos & Migraciones
- `2026_08_05_000008_add_last_login_to_users_table.php`: Tracking de `last_login_at` y `last_login_ip`.
- `2026_08_05_000009_create_user_role_history_table.php`: Auditoría histórica de ascensos y cambios de roles.
- `2026_08_05_000010_create_role_permission_table.php`: Tabla pivote explícita de primera clase entre Roles y Permisos.
- `2026_08_05_000011_create_platform_users_table.php`: Tabla aislada para personal de soporte e ingenieros de la plataforma AIVA (`sanctum:platform`).

### 1.3 Seeder Oficial de Seguridad
- **`IdentityInitializationSeeder`**: Sincroniza automáticamente los Enums en la base de datos, puebla los roles oficiales y asigna permisos mediante `role_permission`.

### 1.4 Servicios Core & Guards
- **`OwnerGuard`**: Guarda de seguridad centralizada que hace cumplir las reglas del propietario (`ensureLastOwnerCannotBeDeleted`, `ensureLastOwnerCannotBeBlocked`, `ensureLastOwnerCannotBeDowngraded`).
- **`PermissionService`**: Evaluación de permisos fuertemente tipada con Enums nativos, precarga en login (Cache Warm-up) e invalidación atómica de versión (`tenant:{tenant_id}:permissions:version`).

### 1.5 Casos de Uso (Actions de la Capa `Application/`)
- `CreateEmployeeAction`: Creación de empleados vinculados al `tenant_id`.
- `UpdateUserStatusAction`: Cambio de estado (`ACTIVE`, `INACTIVE`, `BLOCKED`) protegido por `OwnerGuard`.
- `AssignUserRoleAction`: Cambio de rol con registro en `user_role_history`, despacho del evento `UserRoleChanged` y protección por `OwnerGuard`.
- `TransferOwnershipAction`: Caso de uso atómico para transferir la propiedad del negocio, promoviendo al nuevo usuario a `OWNER`, reasignando al anterior y despachando `OwnershipTransferred`.
- `ListUsersAction`: Búsqueda, paginación y filtrado retornando el contrato `ListUsersResult`.

### 1.6 Middlewares, Controladores y Rutas API
- **`RequirePermission` Middleware**: Middleware con sintaxis limpia para verificación OR/AND:
  - `middleware('permission:any:code1,code2')` (Modo OR)
  - `middleware('permission:all:code1,code2')` (Modo AND)
- **`UserController`**: Endpoints para listar usuarios, crear empleados, actualizar estados, reasignar roles y transferir la propiedad.
- **`RoleController`**: Endpoint `GET /api/v1/identity/roles` para consultar el catálogo de roles y permisos.
- **`AuthenticateUserAction` (Actualizado)**: Tracking de `last_login_at` y `last_login_ip`, emisión del evento `UserLoggedIn` y precarga de caché de permisos (`warmupCache`).

---

## 2. Pruebas Automatizadas

Se agregaron las siguientes suites de pruebas:
- `tests/Feature/OwnerProtectionTest.php`: Verifica que `OwnerGuard` impida bloquear o inhabilitar al único `OWNER` del tenant.
- `tests/Feature/PermissionServiceTest.php`: Verifica la evaluación de permisos usando Enums nativos.
- `tests/Feature/TransferOwnershipTest.php`: Verifica la estructura y flujo DTO de transferencia de propiedad.

---

## 3. Estado de la Arquitectura & Próximo Sprint (Sprint 3)

El **Sprint 2** ha quedado 100% completado, respaldado por la gobernanza de documentación en `docs/` y listo para pasar al **Sprint 3: Business Brain MVP** (Catálogo de Productos, Categorías y Configuración del Negocio por Empresa).
