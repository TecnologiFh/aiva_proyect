<?php

namespace App\Shared\Middleware;

use App\Core\Services\PermissionService;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequirePermission
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    /**
     * Middleware de permisos con sintaxis clara:
     * - permission:any:code1,code2 (Modo OR - requiere al menos uno)
     * - permission:all:code1,code2 (Modo AND - requiere todos)
     * - permission:code1 (Modo simple)
     */
    public function handle(Request $request, Closure $next, ...$parameters)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No autenticado.',
                'errors' => null,
            ], 401);
        }

        if (empty($parameters)) {
            return $next($request);
        }

        $mode = 'all';
        $permissionCodes = [];

        $firstParam = $parameters[0] ?? '';

        if (str_starts_with($firstParam, 'any:')) {
            $mode = 'any';
            $permissionCodes = explode(',', substr($firstParam, 4));
        } elseif (str_starts_with($firstParam, 'all:')) {
            $mode = 'all';
            $permissionCodes = explode(',', substr($firstParam, 4));
        } else {
            $permissionCodes = $parameters;
        }

        $hasAccess = match ($mode) {
            'any' => $this->permissionService->userHasAny($user, $permissionCodes),
            'all' => $this->permissionService->userHasAll($user, $permissionCodes),
        };

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Acceso denegado: No posee los permisos necesarios para realizar esta acción.',
                'errors' => ['required_permissions' => $permissionCodes, 'mode' => $mode],
            ], 403);
        }

        return $next($request);
    }
}
