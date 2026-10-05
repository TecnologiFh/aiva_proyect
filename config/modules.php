<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registro Centralizado de Módulos AIVA
    |--------------------------------------------------------------------------
    |
    | Define el estado de activación de cada módulo del sistema.
    | Permite habilitar o deshabilitar dominios sin alterar la base de código.
    |
    */
    'modules' => [
        'System'       => ['enabled' => true],
        'Tenant'       => ['enabled' => true],
        'Identity'     => ['enabled' => true],
        'Business'     => ['enabled' => true],
        'Conversation' => ['enabled' => true],
        'AI'           => ['enabled' => true],
        'WhatsApp'     => ['enabled' => true],
        'Order'        => ['enabled' => true],
        'Automation'   => ['enabled' => false], // Diferido para Sprint 9
        'Integration'  => ['enabled' => false], // Diferido para Sprint 10
        'Billing'      => ['enabled' => false], // Diferido para Sprint 7
    ]
];
