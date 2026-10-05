<?php

namespace App\Modules\Automation\Models;

use App\Core\Enums\RuleTriggerType;
use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasBinaryUuid, BelongsToTenant;

    protected $table = 'automation_rules';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'trigger_type',
        'trigger_name',
        'cron_expression',
        'logic_tree',
        'actions',
        'priority',
        'stop_processing',
        'is_active',
        'version',
        'executions_count',
        'last_executed_at',
    ];

    protected $casts = [
        'trigger_type' => RuleTriggerType::class,
        'logic_tree' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'stop_processing' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
        'executions_count' => 'integer',
        'last_executed_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'rule_id');
    }
}
