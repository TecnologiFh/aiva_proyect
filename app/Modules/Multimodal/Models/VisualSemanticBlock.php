<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VisualSemanticBlock extends Model
{
    use BelongsToTenant;

    protected $table = 'visual_semantic_blocks';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'asset_id',
        'block_name',
        'block_type',
        'region_box_json',
        'confidence',
    ];

    protected $casts = [
        'region_box_json' => 'array',
        'confidence' => 'float',
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

    public function asset()
    {
        return $this->belongsTo(VisualAsset::class, 'asset_id');
    }

    public function entities()
    {
        return $this->hasMany(VisualEntity::class, 'block_id');
    }
}
