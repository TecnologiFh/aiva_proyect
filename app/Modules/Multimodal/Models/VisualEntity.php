<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VisualEntity extends Model
{
    use BelongsToTenant;

    protected $table = 'visual_entities';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'asset_id',
        'block_id',
        'raw_label',
        'normalized_label',
        'region_box_json',
        'observed_price',
        'currency',
        'extraction_method',
        'evidence_confidence',
        'provenance_classification',
    ];

    protected $casts = [
        'region_box_json' => 'array',
        'observed_price' => 'decimal:2',
        'evidence_confidence' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->normalized_label) && !empty($model->raw_label)) {
                $model->normalized_label = Str::of($model->raw_label)->lower()->ascii()->trim()->squish()->toString();
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(VisualAsset::class, 'asset_id');
    }

    public function block()
    {
        return $this->belongsTo(VisualSemanticBlock::class, 'block_id');
    }

    public function bindings()
    {
        return $this->hasMany(VisualCatalogBinding::class, 'visual_entity_id');
    }
}
