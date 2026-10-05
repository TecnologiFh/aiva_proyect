<?php

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Intelligence\Support\MetricDataStatus;

class ProductsAnalyticsDTO
{
    public function __construct(
        public readonly MetricDataStatus $status,
        public readonly array $catalogSummary,
        public readonly array $orderLinesSummary,
        public readonly array $byCategoryCurrent,
        public readonly array $topProducts,
        public readonly array $period,
        public readonly string $timezone,
        public readonly string $currency,
        public readonly MetricProvenanceDTO $provenance,
        public readonly ?string $message = null
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'catalog_summary' => $this->catalogSummary,
            'order_lines_summary' => $this->orderLinesSummary,
            'by_category_current' => $this->byCategoryCurrent,
            'top_products' => $this->topProducts,
            'period' => $this->period,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'provenance' => $this->provenance->toArray(),
            'message' => $this->message,
        ];
    }
}
