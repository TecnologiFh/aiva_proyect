<?php

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Intelligence\Support\MetricDataStatus;

class SalesAnalyticsDTO
{
    public function __construct(
        public readonly MetricDataStatus $status,
        public readonly array $metrics,
        public readonly array $dimensions,
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
            'metrics' => $this->metrics,
            'dimensions' => $this->dimensions,
            'period' => $this->period,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'provenance' => $this->provenance->toArray(),
            'message' => $this->message,
        ];
    }
}
