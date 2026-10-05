<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhook extends Model
{
    protected $table = 'processed_webhooks';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'provider',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
