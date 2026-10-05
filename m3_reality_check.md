# Reality Check — Módulo 3.1 (WhatsApp Connection) 🚧

He escrito el contrato de aserciones para la conexión física de la cuenta WABA, exponiendo de antemano todas las vulnerabilidades que debemos sellar.

Ejecución: `php artisan test --filter=WhatsAppConnectionTest`

## ⚠️ Estado Inicial (TDD)

| Vector | Estado | Amenaza Expuesta |
| :--- | :--- | :--- |
| **01 Owner can create connection** | 🟡 INCOMPLETE | No existe endpoint ni servicio para conectar el WABA. |
| **02 Connection belongs to Tenant** | 🟡 INCOMPLETE | Riesgo de que la conexión quede huérfana o asignada incorrectamente sin el trait de aislamiento. |
| **03 Unique `waba_id`** | 🟡 INCOMPLETE | Falta restricción física en BD. Un atacante podría secuestrar el WABA de otro negocio intentando registrarlo de nuevo. |
| **04 Unique `phone_number_id`** | 🟡 INCOMPLETE | Misma vulnerabilidad física que el `waba_id`. |
| **05 Cross-tenant isolation** | 🟡 INCOMPLETE | Riesgo de lectura de datos cruzados sin protección del TenantManager en la consulta. |
| **06 Secrets hidden from HTTP** | 🟡 INCOMPLETE | Riesgo de exponer el `access_token` a través de respuestas JSON o peticiones XHR del frontend. |
| **07 Encrypted at rest** | 🟡 INCOMPLETE | Riesgo de Data Breach masivo: si alguien accede a la BD, podría robar los tokens de todos los clientes. |
| **08 Unauthorized modification** | 🟡 INCOMPLETE | Falta de control de acceso a nivel de roles de usuario (solo Owner/Admin debería configurar WABA). |
| **09 Tenant cascade deletion** | 🟡 INCOMPLETE | Riesgo de conexiones fantasma persistiendo en el sistema tras dar de baja a un negocio. |
| **10 Arbitrary `tenant_id` ignored** | 🟡 INCOMPLETE | Riesgo crítico de Insecure Direct Object Reference (IDOR) si el controlador acepta un `tenant_id` enviado por el formulario de la UI. |

### 🗣️ Siguiente Paso Táctico
Nuestra frontera WABA está completamente desprotegida (y no funcional). El objetivo es pintar todo esto de verde.

Para sellar los vectores 03, 04, 07 y 09, nuestro siguiente movimiento en el plan es:
**M3.2 — Migration + WhatsAppConnection Model**
