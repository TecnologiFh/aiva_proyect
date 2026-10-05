# Manual Obligatorio de Desarrollo para Agentes IA (CODING_RULES)

## Estatus: Estricto & Obligatorio

Este documento define las reglas sintácticas, de diseño y arquitectónicas que **todo Agente IA** (y desarrollador humano) debe seguir sin excepción al escribir código en el repositorio de **AIVA**.

---

## 1. Reglas Multi-Tenancy
- **R1.1**: NUNCA realices consultas a modelos empresariales sin asegurar el filtro `tenant_id`. Usa siempre el trait `BelongsToTenant`.
- **R1.2**: NUNCA asumas que existe una sola empresa. Todo dato de negocio pertenece obligatoriamente a un Tenant.

## 2. Reglas de Clean Architecture & Capa Application
- **R2.1**: NUNCA accedas a Modelos Eloquent o Repositorios desde un Controller.
- **R2.2**: Toda acción de modificación o caso de uso debe ser implementada en un **Action** dentro de `Application/` (ej. `CreateTenantAction`).
- **R2.3**: Los Actions solo deben tener un método público ejecutable `execute()`.

## 3. Reglas de Base de Datos y Repositorios
- **R3.1**: NUNCA uses `DB::table()` directamente si existe un Repositorio o Modelo para esa entidad.
- **R3.2**: Todo Repositorio debe ser inyectado mediante su interfaz (`Contract`) del `Core/Contracts/Repositories/`.
- **R3.3**: Las Foreign Keys de base de datos SIEMPRE apuntan a columnas `id` (`BIGINT UNSIGNED`). NUNCA apuntar claves foráneas a `uuid`.

## 4. Reglas de Identificadores y Seguridad
- **R4.1**: NUNCA expongas IDs primarios numéricos auto-incrementales (`id`) en APIs, JSONs de respuesta o URLs.
- **R4.2**: Toda referencia externa a recursos utiliza estrictamente `uuid` en formato string v7/v4 (ej. `d7a2fd77-...`).
- **R4.3**: En la base de datos MySQL, la columna `uuid` es de tipo `BINARY(16)`. El trait `HasBinaryUuid` realiza la conversión bidireccional automática.

## 5. Reglas de Controladores y Respuestas API
- **R5.1**: Los Controladores solo reciben peticiones HTTP, delegan la ejecución al Action mediante DTOs y retornan una respuesta formateada.
- **R5.2**: Toda respuesta de la API debe utilizar la estructura uniforme del helper `ApiResponse`:
  ```json
  {
    "success": true,
    "data": {},
    "message": "Mensaje informativo",
    "errors": null
  }
  ```

## 6. Reglas de Eventos y Auditoría
- **R6.1**: Toda operación que mute datos clave debe registrar auditoría (`AuditService`) y despachar un Domain Event a través de `EventBusInterface`.
- **R6.2**: Los eventos de dominio son hechos pasados en tiempo pretérito (ej. `TenantCreated`, `UserRegistered`).

## 7. Reglas de Testing
- **R7.1**: Ninguna funcionalidad se da por terminada sin contar con pruebas de integración o unitarias (`Pest` / `PHPUnit`).
