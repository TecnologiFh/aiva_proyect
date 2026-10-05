<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaVisualObservation extends Model
{
    protected $table = 'media_visual_observations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'media_asset_id',
        'region_id',
        'raw_text',
        'normalized_text',
        'confidence',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'detected_features_json',
    ];

    protected $casts = [
        'confidence' => 'float',
        'bbox_x' => 'float',
        'bbox_y' => 'float',
        'bbox_w' => 'float',
        'bbox_h' => 'float',
        'detected_features_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->normalized_text) && !empty($model->raw_text)) {
                $model->normalized_text = Str::of($model->raw_text)->lower()->ascii()->trim()->squish()->toString();
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
}
