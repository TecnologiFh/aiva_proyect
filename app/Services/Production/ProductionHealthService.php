<?php

namespace App\Services\Production;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProductionHealthService
{
    /**
     * Endpoint /health/live: Chequeo de supervivencia del proceso PHP/Laravel
     */
    public function checkLiveness(): array
    {
        return [
            'status' => 'live',
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
        ];
    }

    /**
     * Endpoint /health/ready: Chequeo de disponibilidad operativa de infraestructura
     */
    public function checkReadiness(): array
    {
        $dbStatus = 'ok';
        $redisStatus = 'ok';
        $storageStatus = 'ok';
        $isReady = true;

        // 1. Chequeo de Base de Datos MySQL
        try {
            DB::connection()->getPdo();
            DB::table('tenants')->limit(1)->get();
        } catch (\Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
            $isReady = false;
        }

        // 2. Chequeo de Redis / Colas
        try {
            if (config('cache.default') === 'redis') {
                Redis::ping();
            }
        } catch (\Throwable $e) {
            $redisStatus = 'warning: ' . $e->getMessage();
        }

        // 3. Chequeo de Almacenamiento
        if (!is_writable(storage_path())) {
            $storageStatus = 'error: storage path not writable';
            $isReady = false;
        }

        return [
            'status' => $isReady ? 'ready' : 'unready',
            'is_ready' => $isReady,
            'timestamp' => now()->toIso8601String(),
            'components' => [
                'database' => $dbStatus,
                'redis' => $redisStatus,
                'storage' => $storageStatus,
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
            ]
        ];
    }
}
