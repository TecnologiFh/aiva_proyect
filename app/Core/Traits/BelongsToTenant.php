<?php

namespace App\Core\Traits;

use App\Core\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Global Scope automático que filtra consultas por el tenant_id activo
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (TenantContext::hasTenant()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', TenantContext::getTenantId());
            }
        });

        // Asignación automática del tenant_id al crear nuevos registros
        static::creating(function ($model) {
            if (empty($model->tenant_id) && TenantContext::hasTenant()) {
                $model->tenant_id = TenantContext::getTenantId();
            }
        });
    }
}
