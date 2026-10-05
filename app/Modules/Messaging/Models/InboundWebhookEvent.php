<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class InboundWebhookEvent extends Model
{
    protected $table = 'inbound_webhook_events';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'whatsapp_message_id',
        'media_id',
        'status',
        'delivery_status',
        'delivery_weight',
        'delivery_updated_at',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'delivery_updated_at' => 'datetime',
        'delivery_weight' => 'integer',
    ];
}
