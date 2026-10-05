<?php

namespace App\Shared\Middleware;

use App\Core\DTOs\TenantContextDTO;
use App\Core\Exceptions\TenantContextMismatchException;
use App\Core\Exceptions\TenantResolutionException;
use App\Core\Services\TenantContext;
use App\Modules\Messaging\Services\WhatsAppChannelResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GatewayAuthMiddleware
{
    /**
     * Middleware de Autenticación, Verificación de Binding Determinístico y Creación de TenantContextDTO.
     * Implementa política Fail-Closed: 422 TENANT_UNRESOLVED y 409 TENANT_CONTEXT_MISMATCH.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();
        $request->attributes->set('correlation_id', $correlationId);

        // 1. Autenticación por Secreto de Gateway
        $token = $request->bearerToken() ?? $request->header('X-Gateway-Secret') ?? $request->query('gateway_secret');
        $masterSecret = env('AIVA_GATEWAY_SECRET', 'aiva_qr_gateway_master_secret_2026');

        if (empty($token) || $token !== $masterSecret) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNAUTHORIZED_GATEWAY',
                'message' => 'Se requiere encabezado Authorization: Bearer GATEWAY_SECRET válido.',
                'correlation_id' => $correlationId,
            ], 401);
        }

        // 2. Extraer parámetros de sesión e identidad transportados por el Gateway
        $gatewaySessionId = $request->input('gateway_session_id')
            ?? $request->input('client_id')
            ?? $request->header('X-Gateway-Session-ID');

        $rawConnectedPhone = $request->input('connected_phone')
            ?? $request->header('X-Connected-Phone');

        $declaredTenantId = $request->input('tenant_id')
            ?? $request->header('X-Tenant-ID');

        // 3. Resolución Determinística del Tenant oficial mediante WhatsAppChannelResolver
        try {
            $officialTenantId = WhatsAppChannelResolver::resolveStrictBinding(
                $gatewaySessionId,
                $rawConnectedPhone,
                $correlationId
            );
        } catch (TenantResolutionException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'TENANT_UNRESOLVED',
                'message' => $e->getMessage(),
                'correlation_id' => $correlationId,
            ], 422);
        } catch (TenantContextMismatchException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'TENANT_CONTEXT_MISMATCH',
                'message' => $e->getMessage(),
                'correlation_id' => $correlationId,
            ], 409);
        }

        // 4. Principio de Autoridad: Validar si el Gateway declaró un Tenant Claim y contrastarlo
        if (!empty($declaredTenantId) && $declaredTenantId !== 'default') {
            if ((int)$declaredTenantId !== (int)$officialTenantId) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'TENANT_CONTEXT_MISMATCH',
                    'message' => "Discrepancia detectada: El Gateway declaró Tenant [{$declaredTenantId}] pero el binding oficial registrado es [{$officialTenantId}].",
                    'correlation_id' => $correlationId,
                ], 409);
            }
        }

        // 5. Resolver business_id correspondiente al tenant (Ajuste 1: business_id != tenant_id numéricamente)
        $businessId = 0;
        if (Schema::hasTable('business_profiles')) {
            $businessId = (int)(DB::table('business_profiles')
                ->where('tenant_id', $officialTenantId)
                ->value('id') ?? 0);
        }

        // 6. Construir TenantContextDTO inmutable Request-Scoped (TCI-03-A)
        $tenantContext = new TenantContextDTO(
            tenantId: $officialTenantId,
            businessId: $businessId,
            catalogVersionId: null,
            correlationId: $correlationId
        );

        // 7. Establecer en request attributes y contenedor de contexto
        $request->attributes->set('tenant_context', $tenantContext);
        $request->attributes->set('tenant_id', $officialTenantId);
        TenantContext::setContext($tenantContext);

        $response = $next($request);

        // Limpieza de contexto al finalizar el ciclo de vida del request
        TenantContext::clear();

        return $response;
    }
}
