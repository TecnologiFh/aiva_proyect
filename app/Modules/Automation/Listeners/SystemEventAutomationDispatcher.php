<?php

namespace App\Modules\Automation\Listeners;

use App\Modules\Automation\Engine\TriggerDispatcher;

class SystemEventAutomationDispatcher
{
    public function __construct(
        private readonly TriggerDispatcher $triggerDispatcher
    ) {}

    public function handle(object $event): void
    {
        $this->triggerDispatcher->dispatchEvent($event);
    }
}
