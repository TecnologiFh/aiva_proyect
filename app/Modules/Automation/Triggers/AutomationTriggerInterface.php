<?php

namespace App\Modules\Automation\Triggers;

interface AutomationTriggerInterface
{
    /**
     * Evalúa si la clase de evento dada coincide con este disparador.
     */
    public function matches(string $eventName): bool;

    /**
     * Extrae el payload estructurado del evento de dominio.
     */
    public function extractPayload(object $event): array;

    /**
     * Nombre único del disparador (ej. ORDER_CREATED, LOW_STOCK_DETECTED).
     */
    public function getTriggerName(): string;
}
