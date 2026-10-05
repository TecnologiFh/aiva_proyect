# ADR-003: Identificadores Públicos mediante UUID Binario BINARY(16)

## Estatus
Aprobado

## Contexto
Por razones de seguridad, AIVA nunca debe exponer identificadores secuenciales auto-incrementales en URLs o respuestas de API (ej. `/api/v1/tenants/1`). Sin embargo, utilizar `CHAR(36)` para UUIDs en bases de datos relacionales a escala degrada el rendimiento de los índices B-Tree debido a su tamaño (36 bytes por fila) y ordenamiento no secuencial.

## Decisión
Usar identificadores primarios internos `BIGINT UNSIGNED AUTO_INCREMENT` para claves foráneas y rendimiento interno, junto a un campo `uuid` de tipo `BINARY(16)` formateado transparentemente a UUID v7/v4 string en Eloquent mediante el trait `HasBinaryUuid`.

## Consecuencias
### Positivas
- Reducción del 70% en el tamaño de los índices en MySQL en comparación con `CHAR(36)`.
- Seguridad total: Las APIs externas solo reciben y aceptan UUID strings (ej. `d7a2fd77-...`).
- Alta velocidad en búsquedas e inserciones a escala de millones de filas.

### Negativas
- Dificultad para inspeccionar manualmente el campo binario en clientes SQL directos sin usar la función `HEX()` o `UUID_TO_BIN()`.
- Mitigación: Uso de helpers y traits en Eloquent que automatizan la conversión bidireccional.
