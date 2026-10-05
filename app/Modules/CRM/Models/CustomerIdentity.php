<?php

namespace App\Modules\CRM\Models;

use App\Core\Enums\ChannelType;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerIdentity extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_identities';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'channel',
        'identifier',
    ];

    protected $casts = [
        'channel' => ChannelType::class,
    ];
}
