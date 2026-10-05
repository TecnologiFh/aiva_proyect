<?php

namespace App\Modules\Automation\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class AutomationTemplate extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'automation_templates';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'category',
        'content',
    ];
}
