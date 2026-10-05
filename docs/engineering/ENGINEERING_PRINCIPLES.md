# Principios de Ingeniería de AIVA (ENGINEERING_PRINCIPLES)

## Filosofía de Desarrollo del Proyecto

1. **Simplicidad antes que Complejidad**:
   - Preferimos código legible, explícito y sencillo antes que abstracciones prematuras o patrones innecesariamente complejos.
   - El MVP del producto debe ser robusto pero magro (Lean).

2. **Primero Claridad, Después Rendimiento**:
   - Escribir código fácil de entender y mantener por cualquier ingeniero o asistente IA.
   - No realizar optimizaciones prematuras sin contar con métricas concretas de profiling.

3. **Toda Optimización Debe Ser Medida**:
   - Las decisiones sobre índices, cachés o algoritmos se basan en pruebas de carga y métricas reales.

4. **Multi-Tenancy Nativo por Defecto**:
   - Toda característica, módulo o dato creado debe nacer aislado por empresa. No existen datos sin propietario.

5. **Auditable y Testeable por Defecto**:
   - Cada acción clave del sistema debe ser rastreable (Correlation ID, auditoría) y contar con pruebas automatizadas que verifiquen su correcto funcionamiento.

6. **Preservar Compatibilidad de APIs**:
   - Evitar cambios de ruptura (breaking changes) en endpoints expuestos. Versionar siempre mediante `/api/v1/`.
