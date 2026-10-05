<?php

namespace App\Modules\Intelligence\DTOs;

class MetricProvenanceDTO
{
    public function __construct(
        public readonly string $metricName,
        public readonly string $sourceOfTruth,
        public readonly string $calculationSource,
        public readonly array $period,
        public readonly string $timezone,
        public readonly string $currency,
        public readonly string $datasetVersion = 'v1.0',
        public readonly ?string $calculatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'metric_name' => $this->metricName,
            'source_of_truth' => $this->sourceOfTruth,
            'calculation_source' => $this->calculationSource,
            'period' => $this->period,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'dataset_version' => $this->datasetVersion,
            'calculated_at' => $this->calculatedAt ?? now()->toIso8601String(),
        ];
    }
}
