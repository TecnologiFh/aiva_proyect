<?php

namespace App\Modules\CRM\Application\RecordTimelineEvent;

class RecordTimelineEventInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $customerId,
        public readonly string $eventType,
        public readonly string $description,
        public readonly array $metadata = []
    ) {}
}
