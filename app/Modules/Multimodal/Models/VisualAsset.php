<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VisualAsset extends Model
{
    use BelongsToTenant;

    protected $table = 'visual_assets';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'source_file_path',
        'file_hash_sha256',
        'mime_type',
        'dimensions_json',
        'status',
        'metadata_json',
    ];

    protected $casts = [
        'dimensions_json' => 'array',
        'metadata_json' => 'array',
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

    public function blocks()
    {
        return $this->hasMany(VisualSemanticBlock::class, 'asset_id');
    }

    public function entities()
    {
        return $this->hasMany(VisualEntity::class, 'asset_id');
    }

    public function relations()
    {
        return $this->hasMany(VisualRelation::class, 'asset_id');
    }
}
