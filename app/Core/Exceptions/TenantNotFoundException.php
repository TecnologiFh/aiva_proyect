<?php

namespace App\Core\Exceptions;

use Exception;

class TenantNotFoundException extends Exception
{
    protected $message = 'Empresa cliente (Tenant) no encontrada o inactiva.';
    protected $code = 404;
}
