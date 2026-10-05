<?php

namespace App\Modules\Intelligence\Domain\Registry;

use App\Modules\Intelligence\Domain\Calculators\Customer360Calculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersActiveCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersMonetaryValueCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersNewCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersPurchaseFrequencyCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersRecencyCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersRepeatCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersRetentionCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersRfmCalculator;
use App\Modules\Intelligence\Domain\Calculators\CustomersTotalCalculator;
use App\Modules\Intelligence\Domain\Calculators\CategoriesSalesCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductPerformanceCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsAverageRevenuePerOrderCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsOrdersCountCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsRevenueCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsSalesCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsSalesShareCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsTopByQuantityCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsTopByRevenueCalculator;
use App\Modules\Intelligence\Domain\Calculators\ProductsUnitsSoldCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryAvailableStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryLowStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryOutOfStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryStockByProductCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryStockByVariantCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\InventoryTotalStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesAverageOrderValueCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesOrdersCountCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesProductSalesCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesRevenueCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesTopProductsCalculator;
use App\Modules\Intelligence\Domain\Calculators\SalesUnitsSoldCalculator;
use App\Modules\Intelligence\Domain\Calculators\VariantsSalesCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialPaidOrdersCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialPartiallyPaidOrdersCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialUnpaidOrdersCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialCollectedRevenueCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialPaymentCountCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialAveragePaymentCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialPaymentMethodsCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialPaymentMethodShareCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialRefundsCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialRefundedAmountCalculator;
use App\Modules\Intelligence\Domain\Calculators\FinancialNetCollectedCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsOrdersCountCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsOrdersByStatusCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsOrdersByFulfillmentStatusCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsOrderPipelineCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsCancelledOrdersCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsCancellationRateCalculator;
use App\Modules\Intelligence\Domain\Calculators\OperationsCancellationReasonsCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossSalesByCustomerSegmentCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossOrdersByCustomerSegmentCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossRevenueByOrderStatusCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossOrderValueVsCollectedCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossPaymentStatusByFulfillmentStatusCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossCancellationRateByCustomerSegmentCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossProductSalesWithCurrentStockCalculator;
use App\Modules\Intelligence\Domain\Calculators\CrossLowStockSalesExposureCalculator;
use InvalidArgumentException;

class MetricRegistry
{
    /**
     * @var array<string, MetricDefinition>
     */
    private array $metrics = [];

    public function __construct()
    {
        $this->registerDefaultSalesMetrics();
        $this->registerDefaultCustomerMetrics();
        $this->registerDefaultProductMetrics();
        $this->registerDefaultInventoryMetrics();
        $this->registerDefaultFinancialMetrics();
        $this->registerDefaultOperationsMetrics();
        $this->registerDefaultCrossDomainMetrics();
    }

    public function register(MetricDefinition $metric): void
    {
        $this->metrics[$metric->key] = $metric;
    }

    public function get(string $key): MetricDefinition
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException("Métrica no registrada en el catálogo oficial de Intelligence: '{$key}'");
        }
        return $this->metrics[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return array<string, MetricDefinition>
     */
    public function all(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<string, MetricDefinition>
     */
    public function getByCategory(string $category): array
    {
        return array_filter($this->metrics, fn(MetricDefinition $m) => $m->category === $category);
    }

    private function registerDefaultSalesMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'sales.orders_count',
            name: 'Total de Órdenes Calificadas',
            description: 'Conteo canónico de pedidos confirmados y no cancelados en el período.',
            category: 'sales',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: SalesOrdersCountCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'sales.revenue',
            name: 'Ingresos Netos por Ventas',
            description: 'Ventas netas calculadas según la verdad financiera canónica de pedidos y pagos.',
            category: 'sales',
            unit: 'currency',
            source: 'orders + payments + refunds',
            aggregation: 'SUM',
            calculatorClass: SalesRevenueCalculator::class,
            allowedFilters: ['type', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'sales.average_order_value',
            name: 'Ticket Promedio (AOV)',
            description: 'Ingresos netos divididos entre las órdenes calificadas del período.',
            category: 'sales',
            unit: 'currency',
            source: 'orders + payments',
            aggregation: 'AVG',
            calculatorClass: SalesAverageOrderValueCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'sales.units_sold',
            name: 'Unidades Vendidas',
            description: 'Cantidad física total de unidades de productos vendidas en pedidos calificados.',
            category: 'sales',
            unit: 'units',
            source: 'order_items + orders',
            aggregation: 'SUM',
            calculatorClass: SalesUnitsSoldCalculator::class,
            allowedFilters: ['channel', 'payment_method', 'product_id']
        ));

        $this->register(new MetricDefinition(
            key: 'sales.product_sales',
            name: 'Ventas por Producto',
            description: 'Desglose analítico de cantidades e ingresos por producto en el período.',
            category: 'sales',
            unit: 'items',
            source: 'order_items + orders',
            aggregation: 'COMPLEX',
            calculatorClass: SalesProductSalesCalculator::class,
            allowedFilters: ['product_id']
        ));

        $this->register(new MetricDefinition(
            key: 'sales.top_products',
            name: 'Ranking de Productos Más Vendidos',
            description: 'Top N productos ordenados por cantidad o ingresos en el período.',
            category: 'sales',
            unit: 'items',
            source: 'order_items + orders',
            aggregation: 'COMPLEX',
            calculatorClass: SalesTopProductsCalculator::class,
            allowedFilters: ['by', 'limit']
        ));
    }

    private function registerDefaultCustomerMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'customers.total',
            name: 'Total de Clientes Registrados',
            description: 'Conteo total de clientes registrados en el tenant.',
            category: 'customers',
            unit: 'customers',
            source: 'customers',
            aggregation: 'COUNT',
            calculatorClass: CustomersTotalCalculator::class,
            allowedFilters: ['tag', 'preferred_channel']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.new',
            name: 'Nuevos Clientes en el Período',
            description: 'Clientes registrados en el tenant dentro del rango de fechas del período.',
            category: 'customers',
            unit: 'customers',
            source: 'customers',
            aggregation: 'COUNT',
            calculatorClass: CustomersNewCalculator::class,
            allowedFilters: ['tag', 'preferred_channel']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.active',
            name: 'Clientes Activos en el Período',
            description: 'Clientes que realizaron al menos una orden calificable en el período.',
            category: 'customers',
            unit: 'customers',
            source: 'orders',
            aggregation: 'COUNT_DISTINCT',
            calculatorClass: CustomersActiveCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.repeat',
            name: 'Clientes Recurrentes',
            description: 'Clientes con 2 o más pedidos calificables en el período.',
            category: 'customers',
            unit: 'customers',
            source: 'orders',
            aggregation: 'COUNT_DISTINCT',
            calculatorClass: CustomersRepeatCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.purchase_frequency',
            name: 'Frecuencia de Compra',
            description: 'Promedio de pedidos por cliente activo en el período (orders / active_customers).',
            category: 'customers',
            unit: 'orders_per_customer',
            source: 'orders',
            aggregation: 'RATIO',
            calculatorClass: CustomersPurchaseFrequencyCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.recency',
            name: 'Recencia Promedio',
            description: 'Días promedio transcurridos desde la última compra de los clientes activos.',
            category: 'customers',
            unit: 'days',
            source: 'orders',
            aggregation: 'AVG_DATEDIFF',
            calculatorClass: CustomersRecencyCalculator::class,
            allowedFilters: ['channel']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.monetary_value',
            name: 'Valor Monetario Promedio por Cliente',
            description: 'Ingreso promedio generado por cliente activo en el período.',
            category: 'customers',
            unit: 'currency',
            source: 'orders',
            aggregation: 'AVG',
            calculatorClass: CustomersMonetaryValueCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.rfm',
            name: 'Segmentación RFM Canónica',
            description: 'Puntuación y segmentación RFM determinista bajo Policy v1.0.',
            category: 'customers',
            unit: 'segmentation',
            source: 'orders + customers',
            aggregation: 'COMPLEX',
            calculatorClass: CustomersRfmCalculator::class,
            allowedFilters: ['customer_id', 'policy_version']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.360',
            name: 'Customer 360',
            description: 'Vista analítica integral del cliente (historial, AOV, LTV descriptivo y RFM).',
            category: 'customers',
            unit: 'profile',
            source: 'customers + orders',
            aggregation: 'COMPLEX',
            calculatorClass: Customer360Calculator::class,
            allowedFilters: ['customer_id']
        ));

        $this->register(new MetricDefinition(
            key: 'customers.retention',
            name: 'Tasa de Retención de Clientes',
            description: 'Porcentaje de clientes del período anterior que volvieron a comprar en el actual.',
            category: 'customers',
            unit: 'percentage',
            source: 'orders',
            aggregation: 'RATIO_PERCENT',
            calculatorClass: CustomersRetentionCalculator::class,
            allowedFilters: []
        ));
    }

    private function registerDefaultProductMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'products.sales',
            name: 'Desglose de Ventas por Producto',
            description: 'Ventas agregadas de productos con ingresos históricos, unidades y órdenes.',
            category: 'products',
            unit: 'items',
            source: 'order_items + orders + products + categories',
            aggregation: 'COMPLEX',
            calculatorClass: ProductsSalesCalculator::class,
            allowedFilters: ['product_id', 'category_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.units_sold',
            name: 'Unidades Vendidas por Producto',
            description: 'Cantidad física total vendida de productos en órdenes calificadas.',
            category: 'products',
            unit: 'units',
            source: 'order_items + orders',
            aggregation: 'SUM',
            calculatorClass: ProductsUnitsSoldCalculator::class,
            allowedFilters: ['product_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.revenue',
            name: 'Ingresos Históricos por Producto',
            description: 'Ingresos comerciales históricos netos según las líneas de órdenes calificadas.',
            category: 'products',
            unit: 'currency',
            source: 'order_items + orders',
            aggregation: 'SUM',
            calculatorClass: ProductsRevenueCalculator::class,
            allowedFilters: ['product_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.orders_count',
            name: 'Pedidos que Contienen el Producto',
            description: 'Conteo de pedidos únicos que incluyen al menos una unidad del producto.',
            category: 'products',
            unit: 'orders',
            source: 'order_items + orders',
            aggregation: 'COUNT_DISTINCT',
            calculatorClass: ProductsOrdersCountCalculator::class,
            allowedFilters: ['product_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.average_revenue_per_order',
            name: 'Contribución Promedio por Pedido',
            description: 'Ingreso promedio generado por cada pedido que incluye el producto.',
            category: 'products',
            unit: 'currency',
            source: 'order_items + orders',
            aggregation: 'RATIO',
            calculatorClass: ProductsAverageRevenuePerOrderCalculator::class,
            allowedFilters: ['product_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.top_by_quantity',
            name: 'Productos Más Vendidos por Unidades',
            description: 'Ranking de productos ordenados por cantidad vendida en el período.',
            category: 'products',
            unit: 'items',
            source: 'order_items + orders + products',
            aggregation: 'COMPLEX',
            calculatorClass: ProductsTopByQuantityCalculator::class,
            allowedFilters: ['limit', 'category_id', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'products.top_by_revenue',
            name: 'Productos Más Vendidos por Ingresos',
            description: 'Ranking de productos ordenados por ingresos comerciales históricos en el período.',
            category: 'products',
            unit: 'items',
            source: 'order_items + orders + products',
            aggregation: 'COMPLEX',
            calculatorClass: ProductsTopByRevenueCalculator::class,
            allowedFilters: ['limit', 'category_id', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'products.sales_share',
            name: 'Participación en Ventas del Producto',
            description: 'Porcentaje de participación del producto sobre las ventas totales del tenant.',
            category: 'products',
            unit: 'percentage',
            source: 'order_items + orders',
            aggregation: 'RATIO_PERCENT',
            calculatorClass: ProductsSalesShareCalculator::class,
            allowedFilters: ['product_id', 'channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'products.performance',
            name: 'Rendimiento Integral del Producto',
            description: 'Perfil analítico de ventas del producto con comparación temporal y participación.',
            category: 'products',
            unit: 'profile',
            source: 'order_items + orders + products',
            aggregation: 'COMPLEX',
            calculatorClass: ProductPerformanceCalculator::class,
            allowedFilters: ['product_id', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'categories.sales',
            name: 'Ventas por Categoría',
            description: 'Desglose analítico de ventas agrupado por categorías del catálogo.',
            category: 'categories',
            unit: 'items',
            source: 'order_items + products + categories',
            aggregation: 'COMPLEX',
            calculatorClass: CategoriesSalesCalculator::class,
            allowedFilters: ['category_id', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'variants.sales',
            name: 'Ventas por Variante',
            description: 'Desglose de ventas a nivel de variantes de producto (SKU, talla, color).',
            category: 'variants',
            unit: 'items',
            source: 'order_items + product_variants + products',
            aggregation: 'COMPLEX',
            calculatorClass: VariantsSalesCalculator::class,
            allowedFilters: ['product_id']
        ));
    }

    private function registerDefaultInventoryMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'inventory.stock',
            name: 'Stock Actual de Productos',
            description: 'Stock actual y disponible por producto según la autoridad canónica de inventario.',
            category: 'inventory',
            unit: 'items',
            source: 'products + inventory_stocks + product_variants',
            aggregation: 'COMPLEX',
            calculatorClass: InventoryStockCalculator::class,
            allowedFilters: ['product_id', 'category_id', 'status']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.total_stock',
            name: 'Stock Total Disponible',
            description: 'Cantidad física total de unidades en stock en el catálogo del tenant sin duplicación.',
            category: 'inventory',
            unit: 'units',
            source: 'products + inventory_stocks + product_variants',
            aggregation: 'SUM',
            calculatorClass: InventoryTotalStockCalculator::class,
            allowedFilters: ['category_id', 'status']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.available_stock',
            name: 'Stock Neto Disponible (Sin Reservas)',
            description: 'Cantidad total disponible descontando reservas canónicas (current_stock - reserved_stock).',
            category: 'inventory',
            unit: 'units',
            source: 'products + inventory_stocks + product_variants',
            aggregation: 'SUM',
            calculatorClass: InventoryAvailableStockCalculator::class,
            allowedFilters: ['category_id', 'status']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.out_of_stock',
            name: 'Productos Agotados',
            description: 'Conteo de productos cuyo stock disponible es cero o menor.',
            category: 'inventory',
            unit: 'products',
            source: 'products + inventory_stocks + product_variants',
            aggregation: 'COUNT',
            calculatorClass: InventoryOutOfStockCalculator::class,
            allowedFilters: ['category_id']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.low_stock',
            name: 'Productos con Stock Bajo',
            description: 'Conteo de productos cuyo stock disponible es menor o igual al umbral mínimo canónico.',
            category: 'inventory',
            unit: 'products',
            source: 'products + inventory_stocks',
            aggregation: 'COUNT',
            calculatorClass: InventoryLowStockCalculator::class,
            allowedFilters: ['category_id']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.stock_by_product',
            name: 'Desglose de Inventario por Producto',
            description: 'Listado detallado de existencias, reservas y estado de stock por producto.',
            category: 'inventory',
            unit: 'items',
            source: 'products + inventory_stocks + product_variants',
            aggregation: 'COMPLEX',
            calculatorClass: InventoryStockByProductCalculator::class,
            allowedFilters: ['product_id', 'category_id', 'status']
        ));

        $this->register(new MetricDefinition(
            key: 'inventory.stock_by_variant',
            name: 'Desglose de Inventario por Variante',
            description: 'Listado de existencias a nivel de variantes de producto (SKU, talla, color).',
            category: 'inventory',
            unit: 'items',
            source: 'product_variants + products',
            aggregation: 'COMPLEX',
            calculatorClass: InventoryStockByVariantCalculator::class,
            allowedFilters: ['product_id', 'status']
        ));
    }

    private function registerDefaultFinancialMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'financial.paid_orders',
            name: 'Órdenes Pagadas',
            description: 'Conteo de pedidos cuyo estado de pago canónico es PAID.',
            category: 'financial',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: FinancialPaidOrdersCalculator::class,
            allowedFilters: ['payment_method', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.partially_paid_orders',
            name: 'Órdenes Parcialmente Pagadas',
            description: 'Conteo de pedidos cuyo estado de pago canónico es PARTIALLY_PAID.',
            category: 'financial',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: FinancialPartiallyPaidOrdersCalculator::class,
            allowedFilters: ['payment_method', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.unpaid_orders',
            name: 'Órdenes Impagas',
            description: 'Conteo de pedidos con pago pendiente o impago (UNPAID / PENDING).',
            category: 'financial',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: FinancialUnpaidOrdersCalculator::class,
            allowedFilters: ['payment_method', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.collected_revenue',
            name: 'Ingresos Efectivamente Cobrados',
            description: 'Dinero real efectivamente registrado como cobrado y aprobado.',
            category: 'financial',
            unit: 'currency',
            source: 'payments + payment_allocations',
            aggregation: 'SUM',
            calculatorClass: FinancialCollectedRevenueCalculator::class,
            allowedFilters: ['payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.payment_count',
            name: 'Total de Pagos Canónicos',
            description: 'Conteo de transacciones de pago aprobadas sin duplicación por evidencias o webhooks.',
            category: 'financial',
            unit: 'payments',
            source: 'payments + payment_allocations',
            aggregation: 'COUNT',
            calculatorClass: FinancialPaymentCountCalculator::class,
            allowedFilters: ['payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.average_payment',
            name: 'Pago Promedio (Ticket Cobrado)',
            description: 'Monto promedio cobrado por transacción de pago aprobada.',
            category: 'financial',
            unit: 'currency',
            source: 'payments + payment_allocations',
            aggregation: 'AVG',
            calculatorClass: FinancialAveragePaymentCalculator::class,
            allowedFilters: ['payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.payment_methods',
            name: 'Desglose por Métodos de Pago',
            description: 'Distribución de pagos y montos cobrados según el método canónico registrado.',
            category: 'financial',
            unit: 'items',
            source: 'payments + payment_allocations',
            aggregation: 'COMPLEX',
            calculatorClass: FinancialPaymentMethodsCalculator::class,
            allowedFilters: []
        ));

        $this->register(new MetricDefinition(
            key: 'financial.payment_method_share',
            name: 'Participación por Método de Pago',
            description: 'Porcentaje recaudado por un método de pago sobre el total recaudado.',
            category: 'financial',
            unit: 'percentage',
            source: 'payments + payment_allocations',
            aggregation: 'RATIO_PERCENT',
            calculatorClass: FinancialPaymentMethodShareCalculator::class,
            allowedFilters: ['payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.refunds',
            name: 'Total de Reembolsos Procesados',
            description: 'Conteo de devoluciones formalmente procesadas en el período.',
            category: 'financial',
            unit: 'refunds',
            source: 'refunds',
            aggregation: 'COUNT',
            calculatorClass: FinancialRefundsCalculator::class,
            allowedFilters: ['refund_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.refunded_amount',
            name: 'Monto Total Reembolsado',
            description: 'Suma monetaria de reembolsos formalmente procesados.',
            category: 'financial',
            unit: 'currency',
            source: 'refunds',
            aggregation: 'SUM',
            calculatorClass: FinancialRefundedAmountCalculator::class,
            allowedFilters: ['refund_method']
        ));

        $this->register(new MetricDefinition(
            key: 'financial.net_collected',
            name: 'Ingresos Netos Cobrados',
            description: 'Ingresos efectivamente cobrados descontando reembolsos procesados.',
            category: 'financial',
            unit: 'currency',
            source: 'payments + payment_allocations + refunds',
            aggregation: 'COMPLEX',
            calculatorClass: FinancialNetCollectedCalculator::class,
            allowedFilters: ['payment_method']
        ));
    }

    private function registerDefaultOperationsMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'operations.orders_count',
            name: 'Volumen Total de Órdenes',
            description: 'Conteo total de pedidos operados por el tenant en el período.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: OperationsOrdersCountCalculator::class,
            allowedFilters: ['status', 'fulfillment_status', 'payment_status', 'order_type', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'operations.orders_by_status',
            name: 'Distribución por Estado Comercial',
            description: 'Desglose determinístico de órdenes por su estado comercial canónico.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: OperationsOrdersByStatusCalculator::class,
            allowedFilters: ['channel', 'order_type']
        ));

        $this->register(new MetricDefinition(
            key: 'operations.orders_by_fulfillment_status',
            name: 'Distribución por Estado de Fulfillment',
            description: 'Desglose determinístico de órdenes por su estado de ejecución física.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: OperationsOrdersByFulfillmentStatusCalculator::class,
            allowedFilters: ['channel', 'order_type']
        ));

        $this->register(new MetricDefinition(
            key: 'operations.order_pipeline',
            name: 'Pipeline Operativo de Órdenes',
            description: 'Visión consolidada del ciclo operativo de órdenes desde captura hasta entrega o cancelación.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: OperationsOrderPipelineCalculator::class,
            allowedFilters: ['channel', 'order_type']
        ));

        $this->register(new MetricDefinition(
            key: 'operations.cancelled_orders',
            name: 'Total de Órdenes Canceladas',
            description: 'Conteo de pedidos formalmente cancelados en el período según cancelled_at.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'COUNT',
            calculatorClass: OperationsCancelledOrdersCalculator::class,
            allowedFilters: ['cancelled_by', 'cancellation_channel', 'channel'],
            timeDimension: 'cancelled_at'
        ));

        $this->register(new MetricDefinition(
            key: 'operations.cancellation_rate',
            name: 'Tasa de Cancelación de Órdenes',
            description: 'Porcentaje de órdenes creadas en el período que resultaron canceladas (Modelo A: Cohorte de creación).',
            category: 'operations',
            unit: 'percentage',
            source: 'orders',
            aggregation: 'RATIO_PERCENT',
            calculatorClass: OperationsCancellationRateCalculator::class,
            allowedFilters: ['channel', 'order_type']
        ));

        $this->register(new MetricDefinition(
            key: 'operations.cancellation_reasons',
            name: 'Motivos y Atribución de Cancelación',
            description: 'Desglose analítico de cancelaciones según motivo explícito y actor que las ordenó.',
            category: 'operations',
            unit: 'orders',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: OperationsCancellationReasonsCalculator::class,
            allowedFilters: ['channel'],
            timeDimension: 'cancelled_at'
        ));
    }

    private function registerDefaultCrossDomainMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'cross.sales_by_customer_segment',
            name: 'Ventas por Segmento de Cliente',
            description: 'Distribución de ingresos y unidades vendidas por etiqueta/segmento de cliente.',
            category: 'cross_domain',
            unit: 'currency',
            source: 'order_items + orders + customers',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossSalesByCustomerSegmentCalculator::class,
            allowedFilters: ['channel']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.orders_by_customer_segment',
            name: 'Órdenes por Segmento de Cliente',
            description: 'Conteo y volumen monetario de órdenes clasificadas según el segmento del cliente.',
            category: 'cross_domain',
            unit: 'orders',
            source: 'orders + customers',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossOrdersByCustomerSegmentCalculator::class,
            allowedFilters: ['status', 'channel']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.revenue_by_order_status',
            name: 'Ingresos por Estado Operativo',
            description: 'Desglose del volumen comercial y conteo de órdenes según estado comercial y de fulfillment.',
            category: 'cross_domain',
            unit: 'currency',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossRevenueByOrderStatusCalculator::class,
            allowedFilters: ['channel', 'order_type']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.order_value_vs_collected',
            name: 'Comparativa Valor Comercial vs Efectivo Cobrado',
            description: 'Cruce analítico de facturación total contra pagos efectivamente aprobados (P7 Model A).',
            category: 'cross_domain',
            unit: 'currency',
            source: 'orders + payments',
            aggregation: 'COMPLEX',
            calculatorClass: CrossOrderValueVsCollectedCalculator::class,
            allowedFilters: ['channel', 'payment_method']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.payment_status_by_fulfillment_status',
            name: 'Cruce Estado de Pago vs Fulfillment',
            description: 'Observación transversal de la correlación entre ejecución logística y liquidación financiera.',
            category: 'cross_domain',
            unit: 'orders',
            source: 'orders',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossPaymentStatusByFulfillmentStatusCalculator::class,
            allowedFilters: ['channel']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.cancellation_rate_by_customer_segment',
            name: 'Tasa de Cancelación por Segmento de Cliente',
            description: 'Tasa de cancelación cohortal (Modelo A) desglosada por segmento de cliente.',
            category: 'cross_domain',
            unit: 'percentage',
            source: 'orders + customers',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossCancellationRateByCustomerSegmentCalculator::class,
            allowedFilters: ['channel']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.product_sales_with_current_stock',
            name: 'Ventas de Producto con Stock Actual',
            description: 'Cruce entre unidades y valor vendido en el período con la posición de stock actual (P6).',
            category: 'cross_domain',
            unit: 'items',
            source: 'order_items + products + inventory_stocks',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossProductSalesWithCurrentStockCalculator::class,
            allowedFilters: ['product_id', 'category_id']
        ));

        $this->register(new MetricDefinition(
            key: 'cross.low_stock_sales_exposure',
            name: 'Exposición Comercial en Stock Crítico',
            description: 'Productos con demanda activa en el período cuya disponibilidad de stock actual es crítica o nula.',
            category: 'cross_domain',
            unit: 'items',
            source: 'order_items + products + inventory_stocks',
            aggregation: 'BREAKDOWN',
            calculatorClass: CrossLowStockSalesExposureCalculator::class,
            allowedFilters: ['product_id', 'category_id']
        ));
    }
}



