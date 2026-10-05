<?php

namespace App\Modules\Intelligence\DTOs;

use App\Modules\Intelligence\Support\MetricDataStatus;

class CustomersAnalyticsDTO
{
    public const ATTRIBUTION_COMPLETE = 'IDENTITY_ATTRIBUTION_COMPLETE';
    public const ATTRIBUTION_PARTIAL = 'IDENTITY_ATTRIBUTION_PARTIAL';

    public function __construct(
        public readonly MetricDataStatus $status,
        public readonly array $summary,
        public readonly array $attribution,
        public readonly array $byChannel,
        public readonly array $topBuyerIdentities,
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
            'attribution' => $this->attribution,
            'by_channel' => $this->byChannel,
            'top_buyer_identities' => $this->topBuyerIdentities,
            'period' => $this->period,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'provenance' => $this->provenance->toArray(),
            'message' => $this->message,
        ];
    }
}
