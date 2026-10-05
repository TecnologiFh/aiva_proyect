<?php

namespace App\Modules\Multimodal\Runtime\Exceptions;

use Exception;

class AmbiguousVisualEvidenceException extends Exception
{
    /**
     * @param array<string> $candidates
     */
    public function __construct(
        string $message,
        public readonly array $candidates = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
