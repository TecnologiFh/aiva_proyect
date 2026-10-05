<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class MediaSemanticConceptRelation extends Model
{
    protected $table = 'media_semantic_concept_relations';

    protected $fillable = [
        'tenant_id',
        'source_concept_id',
        'relation_type',
        'target_concept_id',
        'strength',
    ];

    protected $casts = [
        'strength' => 'float',
    ];

    public function sourceConcept()
    {
        return $this->belongsTo(MediaSemanticConcept::class, 'source_concept_id');
    }

    public function targetConcept()
    {
        return $this->belongsTo(MediaSemanticConcept::class, 'target_concept_id');
    }
}
