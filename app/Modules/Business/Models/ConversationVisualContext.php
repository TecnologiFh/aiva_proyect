<?php

namespace App\Modules\Business\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationVisualContext extends Model
{
    protected $table = 'conversation_visual_contexts';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'last_media_asset_id',
        'last_media_target',
        'last_media_category',
        'last_media_caption',
        'last_visual_referent',
        'last_media_dispatched_at',
        'context_meta_json',
    ];

    protected $casts = [
        'last_media_dispatched_at' => 'datetime',
        'context_meta_json' => 'array',
    ];

    public function lastMediaAsset()
    {
        return $this->belongsTo(MediaAsset::class, 'last_media_asset_id');
    }
}
