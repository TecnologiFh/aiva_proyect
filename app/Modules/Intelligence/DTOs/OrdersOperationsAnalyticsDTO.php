<?php

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Intelligence\Support\MetricDataStatus;

class OrdersOperationsAnalyticsDTO
{
    public function __construct(
        public readonly MetricDataStatus $status,
        public readonly array $summary,
        public readonly array $byStatus,
        public readonly array $byFulfillmentStatus,
        public readonly array $byDay,
        public readonly array $byChannel,
        public readonly array $cancellations,
        public readonly array $operations,
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
            'summary' => $this->summary,
            'by_status' => $this->byStatus,
            'by_fulfillment_status' => $this->byFulfillmentStatus,
            'by_day' => $this->byDay,
            'by_channel' => $this->byChannel,
            'cancellations' => $this->cancellations,
            'operations' => $this->operations,
            'period' => $this->period,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'provenance' => $this->provenance->toArray(),
            'message' => $this->message,
        ];
    }
}
