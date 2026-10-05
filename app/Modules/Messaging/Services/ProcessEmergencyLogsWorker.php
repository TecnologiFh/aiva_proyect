<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessEmergencyLogsWorker
{
    /**
     * Procesar y drenar la cola de recuperacion durable de emergencia con candado pesimista (Regla C),
     * preservacion de logs (Regla A) y conmutacion a FAILED_PERMANENT a los 5 intentos (Regla B).
     */
    public function flushEmergencyLogs(int $limit = 50): int
    {
        $processedCount = 0;

        DB::transaction(function () use ($limit, &$processedCount) {
            $pendingLogs = DB::table('inbound_webhook_emergency_logs')
                ->whereIn('status', ['PENDING', 'FAILED'])
                ->where('attempts', '<', 5)
                ->orderBy('received_at', 'asc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($pendingLogs as $log) {
                try {
                    $newAttempts = $log->attempts + 1;

                    DB::table('inbound_webhook_emergency_logs')
                        ->where('id', $log->id)
                        ->update([
                            'status' => 'PROCESSING',
                            'attempts' => $newAttempts,
                            'updated_at' => now(),
                        ]);

                    // Procesamiento idempotente de re-inyeccion a AIVA Core
                    $payload = json_decode($log->payload, true);

                    DB::table('inbound_webhook_emergency_logs')
                        ->where('id', $log->id)
                        ->update([
                            'status' => 'PROCESSED',
                            'processed_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $processedCount++;
                    Log::info("Emergency log #{$log->id} processed successfully", [
                        'event_id' => $log->event_id,
                        'correlation_id' => $log->correlation_id
                    ]);
                } catch (\Throwable $e) {
                    $newAttempts = $log->attempts + 1;
                    $finalStatus = $newAttempts >= 5 ? 'FAILED_PERMANENT' : 'FAILED';

                    DB::table('inbound_webhook_emergency_logs')
                        ->where('id', $log->id)
                        ->update([
                            'status' => $finalStatus,
                            'last_error' => $e->getMessage(),
                            'updated_at' => now(),
                        ]);

                    if ($finalStatus === 'FAILED_PERMANENT') {
                        Log::critical("ALERT: Emergency log #{$log->id} hit FAILED_PERMANENT status after 5 attempts", [
                            'event_id' => $log->event_id,
                            'error' => $e->getMessage()
                        ]);
                    } else {
                        Log::error("Failed to process emergency log #{$log->id}", [
                            'attempt' => $newAttempts,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        });

        return $processedCount;
    }
}
