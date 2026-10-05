<?php

namespace App\Modules\Intelligence\Domain\Registry;

class MetricDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $description,
        public readonly string $category,
        public readonly string $unit,
        public readonly string $source,
        public readonly string $aggregation,
        public readonly string $calculatorClass,
        public readonly array $allowedFilters = [],
        public readonly string $timeDimension = 'created_at',
        public readonly string $timezonePolicy = 'tenants.timezone'
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'unit' => $this->unit,
            'source' => $this->source,
            'aggregation' => $this->aggregation,
            'allowed_filters' => $this->allowedFilters,
            'time_dimension' => $this->timeDimension,
            'timezone_policy' => $this->timezonePolicy,
        ];
    }
}
