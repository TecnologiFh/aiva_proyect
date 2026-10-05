<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class MediaSemanticIndex extends Model
{
    protected $table = 'media_semantic_index';

    protected $fillable = [
        'tenant_id',
        'concept_id',
        'media_asset_id',
        'region_id',
        'entity_id',
        'evidence_type',
        'match_score',
        'is_primary',
    ];

    protected $casts = [
        'match_score' => 'float',
        'is_primary' => 'boolean',
    ];

    public function concept()
    {
        return $this->belongsTo(MediaSemanticConcept::class, 'concept_id');
    }

    public function asset()
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function region()
    {
        return $this->belongsTo(MediaVisualRegion::class, 'region_id');
    }

    public function entity()
    {
        return $this->belongsTo(MediaVisualEntity::class, 'entity_id');
    }
}
