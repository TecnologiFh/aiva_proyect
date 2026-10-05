<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PriceDesynchronizationEvidence extends Model
{
    use BelongsToTenant;

    protected $table = 'price_desynchronization_evidence';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'visual_entity_id',
        'canonical_product_identifier',
        'observed_price',
        'authoritative_price',
        'currency',
        'source_asset_hash',
        'catalog_version_id',
        'detected_at',
        'resolution_policy',
        'evidence_payload_json',
    ];

    protected $casts = [
        'observed_price' => 'decimal:2',
        'authoritative_price' => 'decimal:2',
        'detected_at' => 'datetime',
        'evidence_payload_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function entity()
    {
        return $this->belongsTo(VisualEntity::class, 'visual_entity_id');
    }
}
