<?php

namespace App\Modules\Inventory\Jobs;

use App\Modules\Business\Services\BusinessKnowledgeCompiler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DebouncedCompileBusinessBrainJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $tenantId
    ) {}

    public function handle(BusinessKnowledgeCompiler $compiler): void
    {
        $compiler->compile($this->tenantId);
    }
}
