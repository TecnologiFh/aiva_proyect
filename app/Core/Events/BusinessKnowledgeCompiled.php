<?php

namespace App\Core\Events;

use App\Modules\Business\DTOs\CompiledBusinessKnowledge;

class BusinessKnowledgeCompiled
{
    public function __construct(
        public readonly CompiledBusinessKnowledge $knowledge
    ) {}
}
