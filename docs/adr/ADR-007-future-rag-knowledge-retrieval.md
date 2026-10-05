# ADR-007: Arquitectura Futura de Recuperación de Conocimiento (RAG Strategy)

## Estatus
Propuesto / Diferido para Sprint Posterior

## Contexto
El motor de IA de AIVA se alimenta primariamente del snapshot consolidado del **Business Brain** compilado en Redis por `BusinessKnowledgeCompiler`. A medida que AIVA evolucione, los clientes podrán subir archivos extensos (PDFs, manuales de servicio, políticas de garantía complejas o menús masivos).

## Decisión
En el MVP del Sprint 4, el contexto del negocio se inyecta directamente desde el snapshot compilado en Redis (`0ms` latencia, sin costos de cómputo vectorial).

Para fases avanzadas (Sprint 11 - Advanced AI), se mantendrá el snapshot del Business Brain como fuente primaria, pudiendo complementarse mediante un pipeline RAG (Retrieval-Augmented Generation con Embeddings vectoriales) de forma transparente sin alterar la interfaz `AIProviderInterface` ni `PromptContextBuilder`.

## Consecuencias
### Positivas
- Cero sobre-costos y cero latencia de base de datos vectorial en el MVP.
- Mantiene magro y enfocado el flujo comercial (Empresa ➔ Catálogo ➔ IA ➔ WhatsApp ➔ Pedido).
- Arquitectura desacoplada lista para integrar vectores cuando el producto lo justifique.
