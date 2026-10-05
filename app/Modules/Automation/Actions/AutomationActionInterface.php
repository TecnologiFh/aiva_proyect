<?php

namespace App\Modules\Automation\Actions;

interface AutomationActionInterface
{
    /**
     * Ejecuta la acción desacoplada recibiendo parámetros y contexto resuelto.
     */
    public function execute(array $params, array $context): bool;

    /**
     * Retorna el tipo de acción (ej. SEND_WHATSAPP, ADD_CUSTOMER_TAG).
     */
    public function getType(): string;
}
