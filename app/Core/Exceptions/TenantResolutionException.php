<?php

namespace App\Core\Exceptions;

use Exception;

class TenantResolutionException extends Exception
{
    protected $message = 'No fue posible resolver un Tenant activo y determinístico para esta petición.';
    protected $code = 422;
    private string $errorCode = 'TENANT_UNRESOLVED';
    private ?string $correlationId = null;

    public function __construct(string $message = '', ?string $correlationId = null)
    {
        parent::__construct($message ?: $this->message, 422);
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
        ], 422);
    }
}
