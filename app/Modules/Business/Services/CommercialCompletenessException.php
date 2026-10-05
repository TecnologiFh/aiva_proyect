<?php

namespace App\Modules\Business\Services;

class CommercialCompletenessException extends \RuntimeException
{
    public array $validationReport;

    public function __construct(string $message, array $validationReport = [], int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationReport = $validationReport;
    }
}
