<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class MediaVisualRelation extends Model
{
    protected $table = 'media_visual_relations';

    protected $fillable = [
        'tenant_id',
        'media_asset_id',
        'source_entity_id',
        'relation_type',
        'target_entity_id',
        'confidence',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function sourceEntity()
    {
        return $this->belongsTo(MediaVisualEntity::class, 'source_entity_id');
    }

    public function targetEntity()
    {
        return $this->belongsTo(MediaVisualEntity::class, 'target_entity_id');
    }
}
