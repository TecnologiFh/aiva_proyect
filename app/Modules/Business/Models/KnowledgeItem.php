<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeItem extends Model
{
    protected $table = 'knowledge_items';

    protected $fillable = [
        'tenant_id',
        'source_id',
        'item_type',
        'detected_name',
        'detected_category',
        'detected_price',
        'detected_description',
        'extracted_metadata',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'extracted_metadata' => 'array',
        'detected_price' => 'decimal:2',
        'confidence_score' => 'decimal:3',
        'reviewed_at' => 'datetime',
    ];
}
