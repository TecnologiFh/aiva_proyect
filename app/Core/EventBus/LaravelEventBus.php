<?php

namespace App\Core\EventBus;

use App\Core\Contracts\EventBus\EventBusInterface;
use Illuminate\Support\Facades\Event;

class LaravelEventBus implements EventBusInterface
{
    /**
     * Implementación ligera del bus de eventos usando el Dispatcher nativo de Laravel.
     */
    public function dispatch(object $event): void
    {
        Event::dispatch($event);
    }
}
