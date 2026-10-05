<?php

namespace App\Core\Exceptions;

use Exception;

class UnauthorizedTenantAccessException extends Exception
{
    protected $message = 'Acceso denegado: El usuario no tiene permisos para acceder a esta empresa.';
    protected $code = 403;
}
