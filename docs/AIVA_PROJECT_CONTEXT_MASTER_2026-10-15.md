# AIVA Commerce SaaS — Master Context & Continuation Dossier

**Corte documental:** 15 de octubre de 2026  
**Uso:** contexto inicial para Claude, Gemini, sus entornos de actividad/IDE y cualquier agente que continúe la auditoría e implementación de AIVA.  
**Idioma de trabajo:** español.  
**Regla de confianza:** este documento sintetiza decisiones y reportes de auditoría recibidos. No sustituye la verificación del repositorio, los commits, los archivos, los logs ni el estado real de GitHub.

---

## 0. Instrucciones de lectura y estado de confianza

AIVA es un SaaS multi-tenant de comercio y automatización. La prioridad actual es terminar la contención de seguridad transversal, no añadir funcionalidades nuevas ni reconstruir la base de datos prematuramente.

Este dossier distingue:

- **Arquitectura/decisión establecida:** contrato o regla que se ha declarado congelada.
- **Reportado como certificado:** un informe de Arena presenta pruebas y evidencia para una fase. Significa certificado dentro del alcance que ese informe declara, no seguridad integral de AIVA ni verificación independiente de GitHub.
- **Pendiente de verificación externa:** no se ha proporcionado aquí evidencia de que el commit o archivos estén integrados en el repositorio real del usuario/GitHub.
- **Pendiente de ejecución:** existe una orden aprobada, pero todavía no se ha recibido informe de ejecución en el contexto conocido.

### Regla crítica sobre repositorios

Arena utilizó un workspace de auditoría en `/home/user/audit/repo` y evidencia en `/home/user/audit/evidence/`. Ese entorno puede ser aislado o efímero. El repo de trabajo del usuario se ha identificado como `C:\xampp\htdocs\AIVA`, con remoto reportado `git@github.com:TecnologiFh/aiva_proyect.git`.

**No asumir que los commits hechos dentro del workspace de Arena están en el equipo Windows o en GitHub.** Antes de implementar, ejecutar `git rev-parse HEAD`, `git status --short`, `git remote -v` y `git log -10 --oneline` en el repositorio que realmente se va a modificar. Comparar hashes explícitamente y no sincronizar ni sobrescribir automáticamente.

---

## 1. Resumen ejecutivo

AIVA automatiza conversaciones comerciales por WhatsApp, catálogos, pedidos, pagos y operaciones de pequeños negocios mediante una arquitectura multi-tenant. La plataforma debe funcionar para restaurantes, minimarkets, emprendedores y otros negocios sin intervención del desarrollador en sus operaciones normales.

La arquitectura sigue este invariante:

> **LLM proposes/explains; Tools consult; Core decides; DB guarantees; Events notify; infrastructure sustains; telemetry supervises.**

En español: el modelo de lenguaje propone y explica; las herramientas consultan; el Core decide; la base de datos garantiza; los eventos notifican; la infraestructura sostiene; la telemetría supervisa.

La regla significa que el LLM nunca puede ser autoridad de precio, stock, compatibilidad, estado de pedido, estado de pago, pertenencia a tenant ni autorización. El código Core, las políticas explícitas y la base de datos controlan esos hechos.

### Estado general conocido

- Core Contract v21.0: declarado congelado.
- Commerce Contract v21.1: declarado congelado.
- R4-01: contención del endpoint de cambio de roles, reportada como ejecutada.
- R4-02: corrección de `AccessResolver`, reportada como `CERTIFIED`.
- R4-03: endurecimiento de `UserTenantAccessService`, selección de tenant y autoridad de plataforma, reportada como `CERTIFIED`.
- R4-04: enforcement del estado operativo del tenant, reportada como `CERTIFIED` con limitaciones residuales explícitas.
- **R4-05: orden de contención autorizada; no hay todavía un informe final de ejecución en la información recibida.**
- R1 — reconstrucción/certificación de base de datos: **NO INICIADA y NO AUTORIZADA todavía**.
- La suite completa continúa teniendo 123 fallos preexistentes en el entorno de auditoría. La política acordada es comparar los nombres y el orden de los fallos, no declarar éxito por el solo aumento de tests aprobados.

La prioridad inmediata es R4-05: rutas HTTP que realizan mutaciones no autorizadas, endpoints de desarrollo, migración/DDL y mantenimiento. Tras cerrar esa fase, debe reevaluarse el inventario de riesgos y autorizarse cada siguiente fase por separado.

---

## 2. Producto, stack y arquitectura

### 2.1 Propósito

AIVA es un SaaS de ventas y operación por WhatsApp para restaurantes, minimarkets, emprendedores y pequeños negocios. Su objetivo es automatizar atención, consulta de catálogo, toma de pedidos y operaciones sin que un desarrollador deba intervenir en la actividad cotidiana de cada tenant.

El proyecto AIVA es distinto del trabajo personal del usuario sobre POS/CCTV.

### 2.2 Stack reportado

- Laravel 11 (`laravel/framework ^11.0`).
- PHP `^8.2`; destino de producción: PHP 8.3 FPM.
- MySQL 8.0 como objetivo de producción; se rechazó PostgreSQL.
- Entorno de despliegue objetivo: Ubuntu 24.04, NGINX, Redis 7, Supervisor.
- Frontend existente: Blade, Tailwind y JavaScript vanilla.
- Pruebas: PHPUnit/Pest según la suite y convenciones presentes; utilizar la herramienta real configurada en el repositorio.
- Autenticación: Laravel Sanctum en el plano API, guard `web` para usuarios de tenant y guard `platform` para administradores de plataforma, según los reportes de auditoría.

### 2.3 Arquitectura multi-tenant

- Base de datos y esquema compartidos, aislamiento lógico por tenant.
- Identificadores UUID binarios (`BINARY(16)`) en el contrato arquitectónico reportado.
- `TenantContext` y `BelongsToTenant` para contexto y scopes.
- `X-Correlation-ID` para correlación.
- `audit_logs` para auditoría; la implementación de `AuditService::logAction()` tiene limitaciones conocidas en persistencia de `resource/resourceId`.
- Cache con tags por tenant donde se utiliza.
- Sanctum y pruebas automatizadas.
- No usar IDs literales de tenant, sesión o parámetros de petición como prueba de pertenencia.

### 2.4 Módulos identificados

AI, Analytics, Automation, Business, Commerce, Core, CRM (Customer 360), Identity, Inventory, Messaging, Multimodal, Orders, Payments, System y Tenant, además de módulos de soporte e infraestructura.

### 2.5 Capas

La estructura informada distingue `Core`, `Application`, `Modules` y `Shared`. Mantener las decisiones del Core separadas de transportes, controladores y prompts.

---

## 3. Contratos e invariantes congelados

### 3.1 Core Contract v21.0 y Commerce Contract v21.1

Están declarados congelados. No cambiar sus contratos ni reinterpretarlos durante una corrección limitada de seguridad sin orden explícita.

### 3.2 Autoridad de pedido

`OrderStateMachineEngine` es la autoridad prevista para cambios de `status`, `fulfillment_status` y `payment_status`. `OrderCommercialTransitionPolicyService` media las transiciones comerciales.

Reglas conocidas:

- `PAID` requiere una autorización de pago válida del flujo `PAY33...`.
- `PaymentDecisionPolicy` no muta `payment_status`.
- `OrderMutationService::updateOrderTotal()` bloquea cambios de total después de `PAID`.
- Existe un riesgo histórico: aproximadamente 20 escrituras directas de estado fuera de la máquina, y `AutomaticOrderParserService` puede actualizar totales de pedidos pagados. Estos son hallazgos de autoridad de dominio por abordar en sus fases; no corregirlos de paso durante R4-05.
- Conflicto financiero previamente detectado entre `payments.amount` y `payment_allocations.allocated_amount` como fuentes potencialmente divergentes.

### 3.3 Precio y catálogo

`ProductVariant::getEffectivePriceAttribute()` toma `price_override` si existe y, si no, `base_price`. Los parsers deben leer el precio autoritativo desde la base de datos (`products.base_price` según el flujo reportado). El precio nunca se deriva del LLM ni de una imagen.

### 3.4 Identidad y RBAC

- RBAC debe ser fail-closed.
- Tenant activo, pertenencia, rol/permiso y alcance del recurso son comprobaciones distintas.
- La autenticación por sí sola no concede permisos `tenant.*`.
- Un usuario no puede elegir libremente tenant mediante parámetro, header, sesión residual o fallback.
- Los privilegios de plataforma deben permanecer separados de los roles ordinarios del tenant.
- Política de roles acordada: OWNER puede asignar ADMIN dentro de su tenant, sujeto a las protecciones vigentes; ADMIN no puede asignar ADMIN ni OWNER; MANAGER/OPERATOR/AGENT/EMPLOYEE no elevan privilegios; SUPERADMIN es autoridad de plataforma separada.

---

## 4. Historial funcional: fases multimodales y comerciales

### 4.1 P3.10 / P3.10-A — inteligencia visual y carga de catálogos

Se declaró cerrado el fundamento de Vision AI y la ingesta segura de catálogo, incluyendo evidencias sin alucinación. Las pruebas reportadas incluyeron:

- P3.10 A/B de 20/20 con GPT-4o y fallback Gemini 1.5 Flash en esa etapa.
- P3.10-A: 32/32; tablas reportadas `raw_menu_evidences` y `canonical_catalogs`.
- Precios de referencia de productos de un catálogo: Común S/12, Especial S/15, Súper Especial S/17 y Marino S/17.
- La política evolutiva más reciente prohíbe Vision/OCR durante chat normal; el análisis de evidencias de catálogo se lleva por rutas estructuradas.

Los modelos y proveedores disponibles pueden haber cambiado. Verificar configuración actual antes de asumir disponibilidad de un proveedor o modelo.

### 4.2 P3.11 — Commercial Media Intelligence

Trabajo sobre imágenes comerciales de Wallpa Brasa (pollos, chanchos/chaufas, alitas, parrillas) y memoria visual del catálogo. Incidencias previas incluyeron mostrar solo la primera tarjeta y necesidad de soportar variedad visual (por ejemplo prendas con colores, modelos y tallas).

Cierres reportados: P3.11 36/36; AG-34.1 23/23; AG-35, AG-36, AG-37 y AG-37.1; Pixel Firewall y cero Vision runtime en el chat normal.

Tablas/rótulos reportados: `commercial_media`, aliases, `catalog_version_media`, `product_visual_references`. Contexto de media fijado a versión de catálogo.

### 4.3 P3.12 — Multimodal Commercial Intelligence & Visual Memory

La memoria visual no implica que el producto sea vendible. Estados reportados: `MATCH`, `PARTIAL_MATCH`, `VISUAL_ONLY`, `CONFLICT`, `UNRESOLVED`. Prioridad de evidencia: asset exacto > dedicada > relacionada > región general > general.

Wallpa: `wallpa_carta1`–`4`, categorías ALITAS/SALCHIPAPAS/BOX BBQ/CRIOLLO/PARRILLAS. P3.12-R reportado 20/20 con 84 assertions. AG-46: 9/9 hashes de autoridad SKU/precio/stock/compatibilidad; AG-47: 6/6 transporte solamente.

Tipos de evidencia incluidos: Yape/Plin, transferencia bancaria, POS, factura/boleta, carta de menú, imagen de producto, otros/no comerciales.

### 4.4 P3.13 y operaciones

P3.13 reportado como certificado: 6/6 tests, 40 assertions y regresión P3.12-R 20/20. Se eliminó una ruta `/operations` duplicada y se añadieron controles para `UserTenantAccessService` y secuencias de solicitudes.

La resolución de WhatsApp binding debe usar locking donde corresponde, y no debe existir fallback al tenant `wallpa-brasa` si el tenant está sin resolver. Se reportaron resultados para Wallpa (tenant 11), Carenados (tenant 12), Autopartes (tenant 13) y DonutsFs (tenant 4).

### 4.5 Catálogo de carenados para motos — AG-46

Se trabajó con el archivo `Catalogo_Inventario_Carenados.xlsx`. El parser inicial insertó ropa por error, por lo que el esquema lógico del catálogo debe conservar SKU, categoría, modelo, compatibilidad de años, material, color, precio y stock.

Ejemplo de producto del catálogo: `CAR-001`, carenado Honda CB190R, años 2016–2020, S/620, stock 8. Es un ejemplo histórico, no afirmación sobre inventario actual.

### 4.6 Negocios de prueba

- Wallpa Brasa: tenant 11, slug `wallpa-brasa`.
- Donuts Fs: tenant 4, slug registrado anteriormente como `donuts-fs`.
- Carenados: tenant 12.
- Autopartes: tenant 13.

No usar estos IDs para derivar contexto ni codificar fallbacks: son datos de prueba/inventario, no autoridad universal.

---

## 5. Certificaciones del módulo Intelligence

La Foundation de Intelligence (`AIVA-INTELLIGENCE-IMPLEMENTATION-CERT-01`) fue reportada como certificada y verificada el 29-09-2026. P4 Customer 360, P5 Product, P6 Inventory, P7 Financial y P8 Operations fueron reportadas como certificadas/congeladas; P6/P7 el 01-10-2026 y P8 el 01-10-2026.

### 5.1 Contrato P8 Operations

Estados de pedido: `NEW`, `PENDING_CONFIRMATION`, `AWAITING_PAYMENT`, `CONFIRMED`, `COMPLETED`, `CANCELLED`.

Estados de fulfillment: `PENDING`, `PREPARING`, `READY`, `OUT_FOR_DELIVERY`, `DELIVERED`, `CANCELLED`.

Estados de pago: `PENDING`, `PARTIALLY_PAID`, `PAID`, `FAILED`, `REFUNDED`.

Atención: `AI_ACTIVE`, `HUMAN_HANDOVER`, `WAITING_CUSTOMER`, `WAITING_BUSINESS`, `RESOLVED`.

Hallazgos de datos conocidos en aquella certificación:

- `created_at` UTC: 819/819.
- `order_events`: 166 filas / 99 pedidos recientes.
- 13 pedidos cancelados completos.
- Faltaban timestamps de completed/delivered/prepared/state_entered y reglas SLA.
- Métricas aprobadas: `orders_count`, `orders_by_status`, `orders_by_fulfillment_status`, `order_pipeline`, `cancelled_orders`, `cancellation_rate`, `cancellation_reasons`.
- Métricas de tiempo/aging/SLA fueron diferidas.
- Consultas de pedidos deben permanecer en raíz `orders`, sin joins a items/payments/customers para esas métricas certificadas.
- Tenant fail-closed, `TimePeriod`, `MetricRegistry` y `MetricEngine`; suite `P8-T01..T18`.

Estas certificaciones previas no reemplazan la auditoría de seguridad actual ni deben modificarse sin autorización.

---

## 6. Auditoría R0/R4: contexto de seguridad

### 6.1 Estado inicial histórico

El primer `AIVA-FORENSIC-AUDIT-REPORT-01` identificó un estado `CRITICAL / NO-GO`, con numerosos P0/P1: superficies no autenticadas, selección de tenant sin autorización, fallbacks de tenant, backdoor de credenciales, auto-login de SUPERADMIN, DDL accesible por HTTP, esquema no reconstruible y autoridad de pedidos/pagos fragmentada.

El informe inicial correspondía a un baseline más antiguo (`7cf6baf`) y no debe confundirse con los resultados de la línea R4 posterior.

### 6.2 Principales remediaciones históricas

- `cb1b71e`: contención del P0-11: se eliminó DDL de runtime en Customer360, que podía dropear/recrear `customers`; se protegieron `/customers` y `/api/v1/crm/customers`.
- `4da1300`: contención P0-13/14/15: se protegieron rutas de gestión/guardado de usuarios; se retiró la rama de backdoor de credenciales del login y se quitó la creación automática local de un SUPERADMIN por middleware.
- `4dafccc`: contención P0-16: reset de contraseña usa brokers/tokens y almacenes separados para `users` y `platform_users`, tokens de un uso y expiración reportada de 60 minutos.

Riesgo residual histórico: `PlatformAdminSeeder` aún contenía credenciales conocidas de semilla (`admin@aiva.pe` y `donuts@fs.com` con contraseñas reportadas). Rotarlas y gestionar secretos es pendiente independiente; no incluir secretos reales en informes ni commits.

### 6.3 R4-01 — cambio de roles

Commit reportado: `9cf8eef` (`fix(security): contencion AIVA-R4 del escalamiento de privilegios en cambio de rol`), sobre `4dafccc`.

Cambios reportados:

- Ruta `POST /api/v1/settings/users/role` protegida con `auth`.
- El actor se deriva de la identidad autenticada; se eliminó `auth()->id() ?? 1` en ese endpoint.
- Se corrigió el orden de argumentos al `UpdateTenantRoleAction`.
- Validación de nombre de rol contra `RoleType::cases()` y resolución de rol por nombre.
- Comprobaciones OWNER/ADMIN, mismo tenant, no auto-cambio, rango de rol y protecciones del último OWNER.
- Suite específica: 16 tests / 107 assertions, reportados todos aprobados.
- Suite completa tras esa fase reportada: 123 fallos, 182 aprobados, 804 assertions, sin nuevos fallos frente a su baseline.

La política de rango de roles fue añadida como decisión de implementación y debe conservarse como decisión explícita; no permitir que agentes la cambien de forma silenciosa.

### 6.4 R4-02 — AccessResolver

Commit reportado: `585023556a99cc7ce22e888c1d8113bfe584630c`, padre `9cf8eef`, un commit de fase.

Hallazgos corregidos en el informe:

- Concesión tautológica de `tenant.*` por `Auth::check()` en `AccessResolver::userHasPermission()`.
- SUPERADMIN mezclado como rol ordinario de tenant.
- Uso de filas `role_user` sin tenant como fallback.
- `checkResourceScope()` resolvía el rol sin filtrar por tenant.
- `scope()` caía al tenant hardcodeado `4`.
- `NavigationResolver` repoblaba módulos protegidos cuando la lista estaba vacía si el usuario estaba autenticado.

Archivos principales: `app/Services/Authorization/AccessResolver.php`, `app/Services/Navigation/NavigationResolver.php`, suite nueva `AccessResolverAuthorizationR402Test.php` y tres suites adaptadas para eliminar falsos positivos.

Resultados reportados:

- Baseline: 123 fallidos, 182 aprobados, 804 assertions.
- Post R4-02: 123 fallidos, 206 aprobados, 862 assertions.
- R4-02 + R4-01: 40 pruebas aprobadas / 165 assertions.
- Árbol limpio y `git diff --check` limpio en el workspace del informe.

Residuales reportados entonces: `UserTenantAccessService::getAllowedTenants()` fail-open; fallback a tenant 4; `isSuperAdmin()` basado parcialmente en rutas `admin*`; tres servicios con `auth()->id() ?? 1`; `AuditService::logAction()` no persiste `resource/resourceId`; rutas de navegación con entradas incondicionales; divergencia entre códigos `domain:action` de `config/rbac.php` y consumidores `tenant.*`.

### 6.5 R4-03 — pertenencia al tenant y autoridad de plataforma

Commit reportado: `411bc9826b2d36bc3fa41b771c8e88f64fc624ce`, padre `5850235`.

Cambios reportados en `app/Modules/Tenant/Services/UserTenantAccessService.php`:

- `getAllowedTenants()` ya no devuelve todos los tenants si falta identidad/tenant.
- Quitó fallbacks al tenant `4` y `5` del servicio.
- La pertenencia se deriva de `users.tenant_id`, única pertenencia demostrada en el contrato vigente; no se inventó soporte multi-tenant por usuario.
- `validateTenantAccess()` falla de forma cerrada cuando el conjunto autorizado es vacío o no coincide.
- `requireActiveTenant()` dejó de usar `latest_active_tenant_id`, el primer tenant disponible o un literal como sustituto de contexto.
- `isSuperAdmin()` requiere guard `platform`, `PlatformUser::isSuperAdmin()` y estado no suspendido; elimina privilegios inferidos por ruta o sesión.
- Se quitó una mutación en `DashboardConversationController` que reasignaba conversaciones y mensajes de IDs 1..15 al tenant 4.
- Se reparó un consumidor que convertía una denegación en HTTP 500.

Pruebas reportadas:

- R4-03 nueva suite: 28/28, 80 assertions.
- Verificador runtime: 32 PASS / 0 FAIL, con rollback.
- Baseline: 123 fallidos, 206 aprobados, 862 assertions.
- Post: 123 fallidos, 234 aprobados, 942 assertions; 0 nuevas fallas, mismo orden.

Limitación contractual: no se demostró soporte de usuarios con múltiples tenants; no inventar esa capacidad. `tenant_settings.latest_active_tenant_id` aún tenía escritores, pero ya no debía actuar como fuente de contexto.

### 6.6 R4-04 — estado operativo del tenant

Orden: `AIVA-R4-04-TENANT-LIFECYCLE-AUTHORIZATION-ORDER-01`.

Baseline verificado en el workspace de auditoría: `411bc9826b2d36bc3fa41b771c8e88f64fc624ce`.

Commit reportado: `f80e865f99b64eb454f1464004537a8cc55d8a1c`, padre `411bc98`, 13 archivos, +775/-30. Informe entregable reportado: `AIVA-R4-04-TENANT-LIFECYCLE-AUTHORIZATION-REPORT.md` (449 líneas).

Cambios reportados:

- Se endureció `TenantStatusCheckMiddleware`, que existía pero no estaba registrado.
- La identidad ancla el tenant; no se confía en `tenant_id`, `X-Tenant-ID` o sesión como fuente de autoridad.
- Solo estado `ACTIVE` habilita operaciones protegidas; tenant ausente/inexistente, estado vacío o no reconocido → fail-closed.
- Se añadió comprobación de estado a `UserTenantAccessService` mediante `assertTenantOperational()` antes de autorizar y antes de mutar sesión.
- 44 rutas de datos API reportadas con barrera efectiva.
- Logout/perfil/autenticación, webhooks, health, registro público y control de plataforma fueron excluidos del middleware por razones documentadas; las rutas de datos de Identity sí se cubrieron.
- No hubo migraciones, DDL ni cambios de esquema.

Evidencia reportada:

- Suite R4-04: 17 tests aprobados / 138 assertions.
- Runtime verifier: 28 PASS / 0 FAIL.
- Suite completa baseline: 123 fallidos, 234 aprobados, 942 assertions.
- Post-commit: 123 fallidos, 251 aprobados, 1.080 assertions; cuatro corridas con el conjunto ordenado de fallos idéntico, sin nuevas fallas reportadas.
- HTTP decisive proof: sesión creada cuando el tenant estaba activo siguió sirviendo datos antes de la corrección tras la suspensión (HTTP 200); después pasó a 403 con mensaje de tenant `SUSPENDED`. Reactivación restauró acceso legítimo.
- La prueba de fase anterior al commit no conservó el comando exacto y no se utilizó como evidencia concluyente de regresión; el informe lo declaró.
- Una corrida de pruebas dejó el tenant `999` en la base de auditoría desechable por una suite preexistente; el informe dice que se identificó y limpió. La suite completa puede contaminar la DB de pruebas, riesgo aún pendiente.

### 6.7 R4-04: riesgos expresamente pendientes

El informe no cerró toda la seguridad. Residuales informados:

1. `/api/v1/conversations/reset-all-ai`: mutación global sin alcance de tenant y acceso no autenticado informado.
2. `/api/v1/sync-full-wallpa-catalog`: sincronización fijada al tenant `11`, ruta sin autenticación informada.
3. Rutas DDL/PoC/desarrollo tales como `/api/dev/*` y `/dev/migrate-*` pendientes de inventario y contención.
4. Credenciales de seed históricas pendientes de rotación.
5. Tres servicios de gate con `auth()->id() ?? 1`.
6. `AuditService::logAction()` usa `session('active_tenant_id')` para el tenant de auditoría y descarta `resource/resourceId` porque el esquema no tiene esas columnas.
7. El `phpunit.xml` reportado apunta a `aiva_test`, y algunas suites dejan filas residuales.
8. `NavigationResolver` mantiene una entrada incondicional de Business/Knowledge Hub & IA que requiere revisión de autorización.
9. La matriz declarada `domain:action` y los permisos consumidos `tenant.*` siguen divergentes; no se renombraron ni migraron permisos.
10. La ausencia de `personal_access_tokens` en el esquema de auditoría impidió usar tokens persistidos para algunas pruebas API; se utilizó `Sanctum::actingAs`, útil para ejercitar middleware, pero no equivalente a una prueba de integración con Bearer token persistido.

---

## 7. R4-05 — siguiente tarea autorizada y estado actual

### 7.1 Orden emitida

`AIVA-R4-05-UNAUTHENTICATED-MUTATION-AND-DEV-ENDPOINT-CONTAINMENT-ORDER-01`.

El usuario autorizó continuar (“adelante con lo recomendado”). La orden fue redactada y entregada. **En la evidencia recibida no existe todavía un informe de ejecución ni un commit R4-05.** Por tanto se considera AUTORIZADA / PENDIENTE DE EJECUCIÓN O REPORTE, no certificada.

### 7.2 Alcance autorizado

- F1: contener `/api/v1/conversations/reset-all-ai` tras determinar si es operación global administrativa o mutación de un tenant.
- F2: contener `/api/v1/sync-full-wallpa-catalog`, que se reportó fijada al tenant 11.
- F3: inventariar y contener rutas de desarrollo, diagnóstico, migración, DDL, PoC, reconstrucción de datos y otras herramientas peligrosas expuestas por HTTP.
- Inspeccionar mecanismos de autenticación propios de webhooks y no romper flujos legítimos de login/registro/recuperación/health sin analizar el contrato.
- Cada denegación debe demostrarse sin mutación persistente.
- Si el propósito de una ruta no está claro, bloquear la ejecución peligrosa y documentar la decisión pendiente; no inventar semántica de negocio.

### 7.3 Restricciones de R4-05

- No R1.
- No migraciones, DDL, cambios de esquema o datos productivos.
- No reimplementar R4-01 a R4-04.
- No introducir nuevos roles ni una jerarquía RBAC global.
- Un único commit R4-05 sobre el HEAD real verificado de R4-04.
- Suite antes/después, comparación de fallos por nombre y orden, diff y evidencia HTTP/runtime.
- No declarar `CERTIFIED` por número de tests aprobados ni por registrar un middleware.
- No continuar automáticamente a R4-06 ni a R1.

### 7.4 Baseline propuesto para R4-05

El reporte R4-04 indica el commit `f80e865f99b64eb454f1464004537a8cc55d8a1c`. Esto es el hash del workspace de auditoría reportado y debe verificarse en el repositorio que Arena/Claude/Gemini realmente vaya a modificar.

---

## 8. Línea base de pruebas a conservar

Los números siguientes pertenecen al workspace de auditoría y no deben presumirse iguales en el equipo del usuario o en CI hasta reproducirlos:

| Punto de control | Fallidas | Aprobadas | Assertions | Interpretación |
|---|---:|---:|---:|---|
| Tras R4-01 | 123 | 182 | 804 | Baseline de esa fase |
| Baseline R4-02 | 123 | 182 | 804 | Reproducido por Arena |
| Post R4-02 | 123 | 206 | 862 | +24 aprobadas, +58 assertions reportadas |
| Baseline R4-03 | 123 | 206 | 862 | Reproducido |
| Post R4-03 | 123 | 234 | 942 | +28 tests, +80 assertions |
| Baseline R4-04 | 123 | 234 | 942 | Reproducido |
| Post R4-04 | 123 | 251 | 1.080 | +17 tests, +138 assertions |

Interpretación obligatoria: 123 fallos preexistentes permanecen. Un resultado “cero regresiones nuevas” es favorable pero no es una suite limpia ni una certificación integral.

La suite de auditoría se ejecutó en un entorno reportado con PHP 8.3.35 y MariaDB 11.8.6, en `aiva_test` sobre puerto 3308, con 74 tablas y tenants de base 4,5,6,10,11 en el esquema reconstruido. Producción apunta a MySQL 8.0; no confundir MariaDB de auditoría con el entorno productivo objetivo.

**Riesgo de tests:** `phpunit.xml:27` fue señalado por apuntar a `aiva_test`; múltiples corridas han dejado filas residuales en esa DB. Antes de reconstruir o limpiar cualquier DB, confirmar que es desechable y nunca es una base productiva. El plan de largo plazo es pruebas herméticas y aisladas; no cambiar phpunit.xml durante R4-05 salvo autorización específica.

---

## 9. Riesgos de dominio pendientes fuera de R4-05

Estas áreas no deben ser resueltas de forma oportunista en R4-05, pero hay que mantenerlas en el plan global y no avanzar a R1 sin una reevaluación de seguridad:

### 9.1 Autorización/operaciones

- Tres servicios de gate con `auth()->id() ?? 1`: `TenantUxSecurityGateService.php:172`, `GovernanceSecurityGateService.php:300`, `ProductionGoLiveGateService.php:233` según el informe previo.
- Navegación de Knowledge Hub/IA con entrada incondicional, reportada tras R4-02.
- Tenant lifecycle ya corregido por R4-04, pero no se ha verificado independientemente el repo remoto.
- `AuditService::logAction()` no persiste resource/resourceId; usa la sesión para el tenant de auditoría. Evitar confiar en ese registro como prueba completa de alcance hasta corregirlo en fase propia.
- Credenciales de seed conocidas no rotadas en el informe histórico; nunca copiarlas a mensajes públicos ni reintroducirlas en código.
- Endpoints de desarrollo/DDL y mutaciones globales, alcance autorizado de R4-05 aún pendiente de reporte.

### 9.2 Base de datos

- `canonical_catalogs` fue señalado como bloqueador de reconstruibilidad de DB.
- Riesgo de migración cross-tenant y DDL en runtime detectado históricamente; Customer360 runtime DDL fue contenido en `cb1b71e`; la existencia de otras rutas DDL debe comprobarse en R4-05.
- `phpunit.xml` / contaminación de datos y ausencia de hermeticidad.

### 9.3 Autoridad de pedidos y pagos

- Escritores directos de estado de pedidos aparte de `OrderStateMachineEngine`.
- `AutomaticOrderParserService` puede haber modificado totales de pedidos PAID en el análisis previo.
- Autoridad divergente entre `payments.amount` y `payment_allocations.allocated_amount`.

### 9.4 Auditoría/certificación

- No emitir certificaciones globales basadas exclusivamente en pruebas agregadas.
- Las diferencias de entorno deben declararse y no atribuirse a la corrección sin evidencia.
- Cada fase produce un commit y reporte separados; las fases reemitidas no se reimplementan.

---

## 10. Plan de continuación recomendado

Este orden es una propuesta de trabajo basada en los hallazgos conocidos. Las fases futuras necesitan autorización explícita y diagnóstico actualizado.

1. **R4-05 — actualmente autorizada:** contener endpoints de mutación no autenticados y rutas dev/DDL peligrosas; producir reporte y commit único.
2. **Revisión de R4-05:** comprobar diffs, hashes, matrices HTTP, no-mutación, lista completa de rutas y residuos.
3. **R4-06 o fase equivalente — definir después de R4-05:** abordar los riesgos restantes de identidad/auditoría, gates `auth()->id() ?? 1`, navegación de módulos protegidos y/o autenticación/secretos, priorizados con el nuevo inventario. No asignar automáticamente el número R4-06 sin revisar la secuencia y los contratos.
4. **Hermeticidad de pruebas:** diseñar una fase separada para que PHPUnit no contamine bases compartidas, una vez que la prioridad de seguridad crítica esté controlada.
5. **R1 — reconstrucción de base de datos:** solo después de certificar las barreras de autorización críticas y recibir aprobación explícita. En especial resolver `canonical_catalogs`, inventario de migraciones, orden de creación y reconstrucción verificable sin tocar datos productivos.
6. **R2/R3 — autoridad de dominio y financiera:** cerrar las escrituras de estado de pedido, totales tras PAID y fuente autoritativa de importes, mediante órdenes específicas con pruebas de invariantes y un commit por fase.
7. **R5/Intelligence reconciliation:** retomar únicamente después de contratos de DB y dominios certificados; no permitir que métricas o LLM sustituyan el Core o consulten fuera del tenant.

---

## 11. Disciplina de implementación obligatoria para Claude y Gemini

Los agentes pueden actuar como pares: uno puede realizar auditoría forense y el otro revisión independiente. Pero la fuente de verdad sigue siendo el repositorio y los comandos reproducibles, no el acuerdo entre dos modelos.

### Antes de tocar archivos

1. Leer este dossier completo.
2. Confirmar cuál es el repositorio efectivo y si tiene conexión con GitHub.
3. Ejecutar `git rev-parse HEAD`, `git status --short`, `git remote -v`, `git log -10 --oneline`.
4. Comparar el HEAD con el hash esperado; declarar cualquier divergencia.
5. Leer los informes de R4-02/R4-03/R4-04 y ubicar las evidencias en el filesystem real.
6. Reproducir el baseline antes de modificar código, sin inventar resultados si no se puede ejecutar PHP/DB.
7. Inspeccionar el estado de la DB de pruebas. Nunca suponer que `aiva_test` es desechable; confirmarlo.

### Durante la fase

- Una fase, un alcance, un único commit.
- Diagnóstico previo, corrección mínima, pruebas, análisis del diff, commit y reporte.
- No modificar ni reescribir commits anteriores.
- No ejecutar migrations/DDL ni comandos destructivos en DB real.
- No cambiar contratos congelados, ni crear roles o excepciones nuevas de manera implícita.
- No borrar ni deshabilitar pruebas para elevar el número de aprobadas.
- No corregir problemas fuera de alcance dentro del mismo commit.
- Denegación HTTP no basta: verificar ausencia de mutación persistente.
- Pruebas negativas y positivas obligatorias.
- Comparar los tests fallidos por nombre y orden antes/después.
- Si la solución requiere un cambio contractual, detener ese subalcance y reportarlo.
- Si se descubre una vulnerabilidad crítica fuera del alcance, contener solo si la orden lo autoriza; de lo contrario documentar y detener la fase en el punto apropiado.

### Reporte mínimo de cada fase

- Identificador/orden, baseline, hash base y hash final.
- Ambiente, comandos ejecutados y versiones.
- Hallazgos clasificados como hecho, inferencia o limitación.
- Inventario de consumidores/rutas realmente ejecutables.
- Lista de archivos cambiados y justificación por archivo.
- Diff íntegro revisable.
- Pruebas añadidas, pruebas relevantes y resultados completos.
- Conjunto ordenado de fallos baseline/postfix y diferencias explicadas.
- Pruebas HTTP/runtime y snapshots que demuestren no-mutación.
- Declaración explícita sobre migrations, DDL, esquema y datos productivos.
- `git diff --check`, estado del árbol y hash completo del commit.
- Estado: `CERTIFIED`, `PARTIAL`, `BLOCKED` o `STOP`.
- Lista clara de riesgos residuales y siguiente decisión requerida.

No declarar el trabajo “certificado” porque el agente terminó, el diff se ve razonable o pasan las pruebas nuevas. La certificación es por alcance y por evidencia.

---

## 12. Instrucción inicial lista para pegar en Claude/Gemini

> Estás continuando AIVA Commerce SaaS. Lee completamente `AIVA_PROJECT_CONTEXT_MASTER_2026-10-15.md` antes de actuar. Este dossier es contexto, no evidencia de que el filesystem/GitHub actual coincida con los hashes citados.
>
> Tu tarea inmediata autorizada es R4-05: contención de endpoints de mutación no autenticados y rutas de desarrollo/DDL peligrosas. Antes de editar, verifica el repositorio real y su HEAD, árbol, remoto y commits. Contrasta el hash con el baseline reportado de R4-04 (`f80e865f99b64eb454f1464004537a8cc55d8a1c`) y detente si el repo no corresponde o tiene cambios preexistentes que puedan perderse. No asumas que el workspace de Arena está integrado en GitHub.
>
> Empieza con un diagnóstico de solo lectura. Identifica la implementación real y todos los consumidores de `/api/v1/conversations/reset-all-ai`, `/api/v1/sync-full-wallpa-catalog`, las rutas DDL/PoC `/api/dev/*`, `/dev/migrate-*` y cualquier otro endpoint HTTP de mantenimiento con capacidad de mutación. Verifica middleware, efectos SQL y alcance del tenant. Después ejecuta únicamente las correcciones autorizadas por la orden R4-05 original. No cambies el esquema ni datos productivos, no empieces R1, no reimplementes R4-01..R4-04 y no mezcles limpiezas generales.
>
> Exige pruebas negativas y positivas, ausencia de mutación para denegaciones, comparación de la suite completa y del conjunto ordenado de fallos, revisión del diff y un único commit para R4-05. Conserva las 123 fallas preexistentes como referencia hasta demostrar una causa distinta. No debilites ni deshabilites tests. Si existe ambigüedad contractual, bloquea la ejecución insegura y reporta el punto de decisión; no inventes una política de negocio. Al final entrega el informe `AIVA-R4-05-UNAUTHENTICATED-MUTATION-AND-DEV-ENDPOINT-CONTAINMENT-REPORT.md`, con hash completo y estado final. No empieces R4-06 ni R1 automáticamente; espera revisión y autorización explícita.

---

## 13. Glosario breve

- **Tenant:** negocio aislado lógicamente en la base de datos compartida.
- **Fail-closed:** ante identidad, pertenencia, permiso, estado o alcance ausente/no verificable, denegar; nunca conceder por fallback.
- **R4:** secuencia de contención de autorización y seguridad transversal descrita en este dossier.
- **R1:** reconstrucción y certificación de base de datos, bloqueada hasta nueva autorización.
- **AccessResolver:** resolvedor central de permisos de módulos/tenant; R4-02 eliminó la concesión implícita por autenticación.
- **UserTenantAccessService:** servicio que determina tenants permitidos/contexto activo; R4-03 eliminó enumeración fail-open y fallbacks, R4-04 añadió enforcement del estado operativo.
- **TenantStatusCheckMiddleware:** barrera de ciclo de vida; R4-04 lo endureció y reportó 44 rutas de datos API con barrera.
- **R4-05:** autorización vigente en la información aquí reunida, pendiente de evidencia de ejecución.

---

## 14. Nota final sobre alcance y honestidad

Este documento combina contexto estable del proyecto con resultados que Arena reportó. No significa que el repo local del usuario, GitHub, el entorno de Claude y el de Gemini tengan necesariamente el mismo estado.

La primera entrega de cualquier agente debe verificar el repositorio real y registrar el estado observado. Cuando una evidencia no esté disponible, debe declarar “no verificado”; nunca convertir un dato histórico en un hecho actual sin comprobarlo.
