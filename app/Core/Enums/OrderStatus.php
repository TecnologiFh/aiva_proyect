<?php

namespace App\Core\Enums;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case AWAITING_ACCEPTANCE = 'AWAITING_ACCEPTANCE';
    case CONFIRMED = 'CONFIRMED';
    case PREPARING = 'PREPARING';
    case READY = 'READY';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PENDING_CONFIRMATION => 'Pendiente de Confirmación',
            self::AWAITING_ACCEPTANCE => 'Esperando Aceptación del Local',
            self::CONFIRMED => 'Confirmado',
            self::PREPARING => 'En Preparación',
            self::READY => 'Listo para Entrega',
            self::DELIVERED => 'Entregado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
