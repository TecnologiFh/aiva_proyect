<?php

namespace App\Core\Enums;

enum BusinessType: string
{
    case RESTAURANT = 'RESTAURANT';
    case BAKERY = 'BAKERY';
    case PHARMACY = 'PHARMACY';
    case BARBERSHOP = 'BARBERSHOP';
    case CLINIC = 'CLINIC';
    case RETAIL = 'RETAIL';
    case SERVICE = 'SERVICE';

    public function label(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restaurante / Comida',
            self::BAKERY => 'Pastelería / Panadería',
            self::PHARMACY => 'Farmacia / Botica',
            self::BARBERSHOP => 'Barbería / Peluquería',
            self::CLINIC => 'Clínica / Consultorio Médica',
            self::RETAIL => 'Tienda / Minimarket',
            self::SERVICE => 'Servicios Profesionales',
        };
    }
}
