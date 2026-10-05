<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaVisualRegion extends Model
{
    protected $table = 'media_visual_regions';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'media_asset_id',
        'parent_region_id',
        'region_type',
        'label',
        'normalized_label',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'reading_order',
        'confidence',
    ];

    protected $casts = [
        'bbox_x' => 'float',
        'bbox_y' => 'float',
        'bbox_w' => 'float',
        'bbox_h' => 'float',
        'confidence' => 'float',
        'reading_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->normalized_label) && !empty($model->label)) {
                $model->normalized_label = Str::of($model->label)->lower()->ascii()->trim()->squish()->toString();
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function parentRegion()
    {
        return $this->belongsTo(MediaVisualRegion::class, 'parent_region_id');
    }

    public function subRegions()
    {
        return $this->hasMany(MediaVisualRegion::class, 'parent_region_id');
    }

    public function entities()
    {
        return $this->hasMany(MediaVisualEntity::class, 'region_id');
    }
}
