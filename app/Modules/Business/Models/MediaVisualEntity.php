<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaVisualEntity extends Model
{
    protected $table = 'media_visual_entities';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'media_asset_id',
        'region_id',
        'observation_id',
        'entity_type',
        'visual_role',
        'raw_text',
        'normalized_label',
        'confidence',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'meta_json',
    ];

    protected $casts = [
        'confidence' => 'float',
        'bbox_x' => 'float',
        'bbox_y' => 'float',
        'bbox_w' => 'float',
        'bbox_h' => 'float',
        'meta_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->normalized_label) && !empty($model->raw_text)) {
                $model->normalized_label = Str::of($model->raw_text)->lower()->ascii()->trim()->squish()->toString();
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function region()
    {
        return $this->belongsTo(MediaVisualRegion::class, 'region_id');
    }

    public function observation()
    {
        return $this->belongsTo(MediaVisualObservation::class, 'observation_id');
    }

    public function outgoingRelations()
    {
        return $this->hasMany(MediaVisualRelation::class, 'source_entity_id');
    }

    public function incomingRelations()
    {
        return $this->hasMany(MediaVisualRelation::class, 'target_entity_id');
    }
}
