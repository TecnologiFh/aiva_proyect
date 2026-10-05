<?php

namespace App\Core\Exceptions;

use Exception;

class TenantContextMismatchException extends Exception
{
    protected $message = 'Discrepancia de integridad Multi-Tenant detectada entre capas del sistema.';
    protected $code = 409;
    private string $errorCode = 'TENANT_CONTEXT_MISMATCH';
    private ?string $correlationId = null;

    public function __construct(string $message = '', ?string $correlationId = null)
    {
        parent::__construct($message ?: $this->message, 409);
        $this->correlationId = $correlationId;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function render()
    {
        return response()->json([
            'status' => 'error',
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'correlation_id' => $this->correlationId,
        ], 409);
    }
}
