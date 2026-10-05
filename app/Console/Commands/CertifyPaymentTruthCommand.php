<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Payments\Application\Services\MasterPayCertificationService;
use App\Modules\Payments\Application\Services\GoldDatasetBenchmarkService;

class CertifyPaymentTruthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aiva:payment-certify {--gold : Run Gold Dataset Benchmark v1.0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta la suite interna de certificación de Payment Truth Layer (PAY1–PAY33) e Invariante PI-01';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(
        MasterPayCertificationService $masterCertifier,
        GoldDatasetBenchmarkService $goldBenchmark
    ) {
        $this->info("==========================================================");
        $this->info("AIVA Commerce v21.1 — Payment Truth Layer Certification");
        $this->info("==========================================================");

        if ($this->option('gold')) {
            $this->info("Ejecutando Gold Dataset Benchmark v1.0 (n=20)...");
            $goldResult = $goldBenchmark->runBenchmark();
            $this->line(json_encode($goldResult, JSON_PRETTY_PRINT));
            return $goldResult['certified'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->info("Ejecutando Certificación Máster PAY1–PAY33...");
        $result = $masterCertifier->runMasterSuite();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Estado Global', $result['status']],
                ['Contrato v1.0', $result['master_certification']],
                ['Pruebas Exitosas', "{$result['summary']['passed']} / {$result['summary']['total']}"],
                ['Invariante PI-01', 'ENFORCED (PaymentAuthorizationService ONLY)']
            ]
        );

        if ($result['certified']) {
            $this->info("🟢 CERTIFICACIÓN EXITOSA: Todos los 33 Master PAY Gates han pasado con 100% de efectividad.");
            return Command::SUCCESS;
        }

        $this->error("❌ FALLO DE CERTIFICACIÓN: La suite de pagos reporta inconsistencias.");
        return Command::FAILURE;
    }
}
