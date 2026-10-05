<?php

namespace App\Core\Contracts\EventBus;

interface EventBusInterface
{
    /**
     * Despacha un evento de dominio al bus del sistema.
     */
    public function dispatch(object $event): void;
}
