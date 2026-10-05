<?php

namespace App\Core\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case TRIAL = 'TRIAL';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::INACTIVE => 'Inactivo',
            self::SUSPENDED => 'Suspendido',
            self::TRIAL => 'En prueba',
            self::EXPIRED => 'Expirado',
        };
    }
}
