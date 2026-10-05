<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorrelationIdMiddleware
{
    /**
     * Inyectar y propagar X-Correlation-ID por solicitud HTTP.
     */
    public function handle(Request $request, Closure $next)
    {
        $headerName = 'X-Correlation-ID';
        
        $correlationId = $request->header($headerName) 
            ?: 'aiva-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(8)), 0, 12);

        // Fijar en el contenedor de Laravel y en la petición actual
        app()->instance('correlation_id', $correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        $response = $next($request);

        // Añadir el encabezado X-Correlation-ID en la respuesta HTTP
        if (method_exists($response, 'header')) {
            $response->header($headerName, $correlationId);
        }

        return $response;
    }
}
