<?php

namespace App\Modules\System\Controllers;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $dbStatus = 'healthy';
        $redisStatus = 'healthy';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'unhealthy: ' . $e->getMessage();
        }

        try {
            Redis::connection('cache')->ping();
        } catch (\Throwable $e) {
            $redisStatus = 'unhealthy: ' . $e->getMessage();
        }

        $isHealthy = $dbStatus === 'healthy' && $redisStatus === 'healthy';

        $data = [
            'status' => $isHealthy ? 'UP' : 'DOWN',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbStatus,
                'redis_cache' => $redisStatus,
            ],
        ];

        return ApiResponse::success(
            data: $data,
            message: $isHealthy ? 'Todos los sistemas están operando normalmente' : 'Se detectaron degradaciones en los servicios',
            code: $isHealthy ? 200 : 503
        );
    }
}
