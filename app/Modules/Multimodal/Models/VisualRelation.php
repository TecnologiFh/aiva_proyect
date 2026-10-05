<?php

namespace App\Modules\Multimodal\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class VisualRelation extends Model
{
    use BelongsToTenant;

    protected $table = 'visual_relations';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'source_entity_id',
        'target_entity_id',
        'relation_type',
        'confidence',
        'metadata_json',
    ];

    protected $casts = [
        'confidence' => 'float',
        'metadata_json' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(VisualAsset::class, 'asset_id');
    }

    public function sourceEntity()
    {
        return $this->belongsTo(VisualEntity::class, 'source_entity_id');
    }

    public function targetEntity()
    {
        return $this->belongsTo(VisualEntity::class, 'target_entity_id');
    }
}
