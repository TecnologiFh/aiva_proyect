<?php

namespace App\Modules\Intelligence\Domain\Values;

use App\Modules\Intelligence\Support\MetricDataStatus;

class IntelligenceResult
{
    public readonly string $status;

    public function __construct(
        public readonly string $metric,
        public readonly string $metricName,
        public readonly mixed $value,
        public readonly string $unit,
        string|MetricDataStatus $status = MetricDataStatus::KNOWN,
        public readonly array $period = [],
        public readonly int $tenantId = 0,
        public readonly ?PeriodComparison $comparison = null,
        public readonly array $provenance = [],
        public readonly ?string $message = null
    ) {
        $this->status = $status instanceof MetricDataStatus ? $status->value : $status;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'metric' => $this->metric,
            'metric_name' => $this->metricName,
            'value' => $this->value,
            'unit' => $this->unit,
            'tenant_id' => $this->tenantId,
            'period' => $this->period,
            'comparison' => $this->comparison?->toArray(),
            'provenance' => $this->provenance,
            'message' => $this->message,
        ];
    }
}
