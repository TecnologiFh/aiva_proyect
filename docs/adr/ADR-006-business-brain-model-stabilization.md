# ADR-006: Estabilización del Modelo del Business Brain MVP

## Estatus
Aprobado & Congelado

## Contexto
El módulo **Business Brain** representa el modelo de conocimiento comercial que nutrirá al Motor de Inteligencia Artificial (Sprint 4) y al Conector de WhatsApp (Sprint 5). Habiendo validado la inclusión de Opciones de Producto, `base_price`, `search_keywords`, `ProductType`, `BusinessType`, `business_settings` flexibles con `preferences JSON`, `business_hours` multiturno y la compilación con puntero atómico en Redis (`BusinessKnowledgeCompiler`), el modelo ha alcanzado la madurez necesaria.

## Decisión
Congelar oficialmente la arquitectura de datos del **Business Brain MVP** para el Sprint 3. No se realizarán más modificaciones estructurales a los esquemas de productos, categorías y configuraciones antes de la implementación del código.

Toda evolución futura responderá a la retroalimentación de uso del motor de IA en los Sprints 4 y 5.

## Consecuencias
### Positivas
- Estabilidad total para proceder con la codificación de migraciones, modelos, repositorios, Actions y compiladores.
- Garantía de respuesta en 0ms para la IA mediante el documento compilado en Redis (`tenant:{id}:brain:knowledge:current`).
