<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VisualCatalogBinding extends Model
{
    use BelongsToTenant;

    protected $table = 'visual_catalog_bindings';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'visual_entity_id',
        'canonical_product_identifier',
        'catalog_version_id',
        'resolution_state',
        'temporal_status',
        'observed_price',
        'authoritative_price',
        'divergence_flag',
        'last_reconciled_at',
    ];

    protected $casts = [
        'observed_price' => 'decimal:2',
        'authoritative_price' => 'decimal:2',
        'divergence_flag' => 'boolean',
        'last_reconciled_at' => 'datetime',
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
