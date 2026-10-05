<?php

namespace App\Core\Enums;

enum CustomerIntent: string
{
    case PURCHASE_INTENT = 'PURCHASE_INTENT';
    case PRODUCT_INQUIRY_INTENT = 'PRODUCT_INQUIRY_INTENT';
    case HOURS_INQUIRY_INTENT = 'HOURS_INQUIRY_INTENT';
    case GREETING_INTENT = 'GREETING_INTENT';
    case HUMAN_TAKEOVER_INTENT = 'HUMAN_TAKEOVER_INTENT';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_INTENT => 'Intención de Compra / Pedido',
            self::PRODUCT_INQUIRY_INTENT => 'Consulta de Productos / Precios',
            self::HOURS_INQUIRY_INTENT => 'Consulta de Horarios / Cobertura',
            self::GREETING_INTENT => 'Saludo / Despedida',
            self::HUMAN_TAKEOVER_INTENT => 'Derivación a Atención Humana',
        };
    }
}
