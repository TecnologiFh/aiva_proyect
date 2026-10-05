<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Business\Models\CommercialMedia;
use App\Modules\Business\Models\CatalogVersion;

class ConversationCommercialContext extends Model
{
    protected $table = 'conversation_commercial_contexts';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'catalog_version_id',
        'last_commercial_media_id',
        'last_category',
        'last_display_order',
    ];

    public function commercialMedia()
    {
        return $this->belongsTo(CommercialMedia::class, 'last_commercial_media_id');
    }

    public function catalogVersion()
    {
        return $this->belongsTo(CatalogVersion::class, 'catalog_version_id');
    }
}
