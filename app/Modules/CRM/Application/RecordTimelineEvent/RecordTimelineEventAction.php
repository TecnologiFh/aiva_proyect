<?php

namespace App\Modules\CRM\Application\RecordTimelineEvent;

use App\Modules\CRM\Models\CustomerTimeline;

class RecordTimelineEventAction
{
    public function execute(RecordTimelineEventInput $input): CustomerTimeline
    {
        return CustomerTimeline::create([
            'tenant_id' => $input->tenantId,
            'customer_id' => $input->customerId,
            'event_type' => $input->eventType,
            'description' => $input->description,
            'metadata' => $input->metadata,
            'created_at' => now(),
        ]);
    }
}
