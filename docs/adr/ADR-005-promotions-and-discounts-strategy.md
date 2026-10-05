# ADR-005: Estrategia de Promociones y Descuentos Futuros

## Estatus
Propuesto / Diferido para Sprint Posterior

## Contexto
Los negocios clientes de AIVA (restaurantes, minimarkets, tiendas) requieren estrategias comerciales dinámicas como promociones 2x1, Happy Hours, cupones de descuento, combos familiares o descuentos por volumen.

## Decisión
Para mantener la simplicidad y el enfoque Lean del MVP en el Sprint 3, las promociones complejas no se construyen en las tablas del catálogo primario (`products`, `categories`).

El catálogo base utiliza `base_price`. En fases posteriores, un motor desacoplado de promociones (`PromotionEngine`) interceptará los precios al momento de crear el carrito u orden, aplicando las reglas de descuento sobre la orden sin alterar el precio base del producto.

## Consecuencias
### Positivas
- Cero sobre-ingeniería en el MVP del Sprint 3.
- Mantiene limpia la estructura de catálogo para el consumo rápido del motor de IA.
- Permite construir reglas de descuento complejas (cupones, horarios, membresías) de forma desacoplada en el futuro.
