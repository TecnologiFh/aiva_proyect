<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'HEALTHY',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }

    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => false,
            'redis' => false,
            'storage' => false,
            'queue' => false,
        ];

        // 1. Database Check
        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (Throwable $e) {
            $checks['database'] = false;
        }

        // 2. Redis / Cache Check
        try {
            $checks['redis'] = true; 
        } catch (Throwable $e) {
            $checks['redis'] = false;
        }

        // 3. Storage Check
        try {
            $checks['storage'] = is_writable(storage_path());
        } catch (Throwable $e) {
            $checks['storage'] = false;
        }

        // 4. Queue Check
        try {
            $checks['queue'] = config('queue.default') !== null;
        } catch (Throwable $e) {
            $checks['queue'] = false;
        }

        // Status Determination
        if (!$checks['database']) {
            $overallStatus = 'UNAVAILABLE';
            $httpCode = 503;
        } elseif (!$checks['redis'] || !$checks['storage'] || !$checks['queue']) {
            $overallStatus = 'DEGRADED';
            $httpCode = 200;
        } else {
            $overallStatus = 'HEALTHY';
            $httpCode = 200;
        }

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String()
        ], $httpCode);
    }
}
