<?php

namespace App\Modules\Automation\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AutomationLog extends Model
{
    use BelongsToTenant;

    protected $table = 'automation_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'rule_id',
        'rule_version',
        'trigger_name',
        'status',
        'execution_details',
        'executed_at',
    ];

    protected $casts = [
        'rule_version' => 'integer',
        'execution_details' => 'array',
        'executed_at' => 'datetime',
    ];
}
