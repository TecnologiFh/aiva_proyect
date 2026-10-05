<?php

namespace App\Console\Commands;

use App\Modules\Messaging\Services\OutboxDispatcherWorker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'aiva:outbox-dispatcher 
                            {--tenant= : ID del tenant específico a procesar}
                            {--limit=10 : Límite de mensajes por lote}
                            {--daemon : Ejecutar en bucle continuo}';

    /**
     * The console command description.
     */
    protected $description = 'Procesa y despacha mensajes pendientes de la tabla transactional outbox_messages hacia Meta';

    /**
     * Execute the console command.
     */
    public function handle(OutboxDispatcherWorker $worker): int
    {
        $tenantOpt = $this->option('tenant');
        $limit = (int) $this->option('limit');
        $isDaemon = (bool) $this->option('daemon');

        do {
            $tenants = $tenantOpt 
                ? [(int) $tenantOpt] 
                : DB::table('tenants')->where('status', 'ACTIVE')->pluck('id')->all();

            $totalProcessed = 0;
            foreach ($tenants as $tId) {
                $processed = $worker->processBatch((int) $tId, $limit);
                $totalProcessed += count($processed);
            }

            if ($isDaemon) {
                if ($totalProcessed === 0) {
                    sleep(2);
                }
            }
        } while ($isDaemon);

        return Command::SUCCESS;
    }
}
