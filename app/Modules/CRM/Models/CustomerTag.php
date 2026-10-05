<?php

namespace App\Modules\CRM\Models;

use App\Core\Enums\TagType;
use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerTag extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_tags';

    protected $fillable = [
        'tenant_id',
        'name',
        'tag_type',
        'color',
    ];

    protected $casts = [
        'tag_type' => TagType::class,
    ];
}
