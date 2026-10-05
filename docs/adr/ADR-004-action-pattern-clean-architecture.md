# ADR-004: Patrón Action (Capa Application) para Clean Architecture

## Estatus
Aprobado

## Contexto
En sistemas SaaS complejos, los "Services" tienden a convertirse en archivos masivos de miles de líneas ("God Classes"), mezclando múltiples responsabilidades y haciendo imposible el testing y la colaboración fluida entre desarrolladores y agentes IA.

## Decisión
Adoptamos la Capa `Application/` basada en **Actions de Responsabilidad Única**.
Cada caso de uso vive aislado en su propio directorio (ej. `Modules/Tenant/Application/CreateTenant/CreateTenantAction.php`).

El flujo de ejecución es estrictamente:
`Controller` ➔ `Action (Application)` ➔ `Repository (Core Contract)` ➔ `Model`.

## Consecuencias
### Positivas
- Cero "God Services". Cada clase tiene un único método `execute()`.
- Facilidad absoluta para pruebas unitarias de casos de uso sin levantar todo el framework.
- Claridad total para asistentes de IA y desarrolladores al agregar o modificar un flujo.

### Negativas
- Mayor número de clases y archivos pequeños en el proyecto.
- Mitigación: Estructura de carpetas por caso de uso limpia y estandarizada.
