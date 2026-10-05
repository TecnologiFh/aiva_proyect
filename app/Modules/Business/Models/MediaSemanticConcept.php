<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaSemanticConcept extends Model
{
    protected $table = 'media_semantic_concepts';

    protected $fillable = [
        'tenant_id',
        'concept',
        'normalized_concept',
        'concept_type',
        'relevance_weight',
    ];

    protected $casts = [
        'relevance_weight' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->normalized_concept) && !empty($model->concept)) {
                $model->normalized_concept = Str::of($model->concept)->lower()->ascii()->trim()->squish()->toString();
            }
        });
    }

    public function relatedConcepts()
    {
        return $this->hasMany(MediaSemanticConceptRelation::class, 'source_concept_id');
    }

    public function indexEntries()
    {
        return $this->hasMany(MediaSemanticIndex::class, 'concept_id');
    }
}
