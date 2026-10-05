<?php

namespace App\Core\Traits;

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

trait HasBinaryUuid
{
    public static function bootHasBinaryUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Mutador al asignar uuid (asegura String UUID estándar de 36 caracteres para columnas char(36)).
     */
    public function setUuidAttribute($value): void
    {
        if (is_string($value) && strlen($value) === 16) {
            try {
                $this->attributes['uuid'] = Uuid::fromBytes($value)->toString();
                return;
            } catch (\Throwable) {
                // fallback
            }
        }

        $this->attributes['uuid'] = $value;
    }

    /**
     * Accesor al obtener uuid (convierte BINARY 16 bytes a String UUID si fuera necesario).
     */
    public function getUuidAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && strlen($value) === 16) {
            try {
                return Uuid::fromBytes($value)->toString();
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Scope para buscar por UUID String.
     */
    public function scopeWhereUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
