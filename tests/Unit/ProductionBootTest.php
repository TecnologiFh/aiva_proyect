<?php

namespace Tests\Unit;

use App\Modules\Messaging\Services\ProcessEmergencyLogsWorker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionBootTest extends TestCase
{
    /**
     * C0-1 Acceptance Test: Config Cache & Config Services
     */
    public function test_c0_1_config_cache_compatibility()
    {
        $provider = config('aiva.ai.provider');
        $this->assertNotEmpty($provider, 'Config key aiva.ai.provider no debe ser nula ni estar vacia.');
        $this->assertContains(strtoupper($provider), ['GROQ', 'OPENAI', 'GEMINI', 'OLLAMA', 'DEEPSEEK']);
    }

    /**
     * C0-2 Acceptance Test: Durable Emergency Log Queue & Fail-Safe Recovery
     */
    public function test_c0_2_emergency_log_recovery_worker()
    {
        $tenantId = 4;
        $eventId = 'EVT-TEST-' . rand(1000, 9999);

        // Insertar log de emergencia simulando caida de Redis
        DB::table('inbound_webhook_emergency_logs')->insert([
            'event_id' => $eventId,
            'tenant_id' => $tenantId,
            'payload_hash' => hash('sha256', 'payload_data'),
            'payload' => json_encode(['event' => 'whatsapp_inbound', 'message' => 'test']),
            'status' => 'PENDING',
            'attempts' => 0,
            'correlation_id' => 'CORR-TEST-123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $worker = new ProcessEmergencyLogsWorker();
        $flushed = $worker->flushEmergencyLogs(10);

        $this->assertGreaterThanOrEqual(1, $flushed);

        $logAfter = DB::table('inbound_webhook_emergency_logs')->where('event_id', $eventId)->first();
        $this->assertEquals('PROCESSED', $logAfter->status);
        $this->assertNotNull($logAfter->processed_at);
    }

    /**
     * C0-5 Acceptance Test: Secret Shield & .gitignore Verification
     */
    public function test_c0_5_secret_management_gitignore()
    {
        $gitignoreFile = base_path('.gitignore');
        $this->assertFileExists($gitignoreFile);

        $gitignoreContent = file_get_contents($gitignoreFile);
        $this->assertStringContainsString('.env', $gitignoreContent, 'El archivo .gitignore debe incluir .env para evitar fuga de secretos.');
    }
}
