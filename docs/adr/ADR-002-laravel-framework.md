# ADR-002: Elección de Laravel Framework sobre PHP 8.2+

## Estatus
Aprobado

## Contexto
El backend de AIVA requiere procesar eventos, APIs RESTful, colas asíncronas (Redis), tareas programadas, integración con pasarelas e IA, manteniéndose robusto, seguro y fácil de auditar.

## Decisión
Seleccionamos **Laravel 11** como framework base del backend.

## Consecuencias
### Positivas
- Ecosistema maduro para SaaS (Sanctum para API tokens, Horizon para colas Redis, Pest/PHPUnit para testing).
- ORM Eloquent potente y extensible para patrones de repositorio y traits globales.
- Soporte nativo para Enums de PHP 8.1+, Middlewares, Form Requests y Dependency Injection.

### Negativas
- Potencial sobrecarga de memoria si no se estructuran correctamente los servicios.
- Mitigación: Estructura modular por dominios (`app/Modules/`) y arquitectura limpia con desacoplamiento en la capa `Application`.
