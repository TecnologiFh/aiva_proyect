<?php

namespace Tests\Unit;

use App\Modules\Inventory\Services\DailyStockManager;
use App\Modules\Messaging\Tools\GetProductAvailabilityTool;
use App\Modules\Messaging\Tools\GetProductInfoTool;
use App\Modules\Orders\Services\PaymentVoucherEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommerceExpansionPR14PR23Test extends TestCase
{
    protected DailyStockManager $stockManager;
    protected GetProductAvailabilityTool $availabilityTool;
    protected GetProductInfoTool $infoTool;
    protected PaymentVoucherEngine $voucherEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockManager = new DailyStockManager();
        $this->availabilityTool = new GetProductAvailabilityTool($this->stockManager);
        $this->infoTool = new GetProductInfoTool();
        $this->voucherEngine = new PaymentVoucherEngine();
    }

    /**
     * PR14-A: Invariante Matemática del Stock (initial = available + reserved + sold)
     */
    public function test_pr14_a_stock_mathematical_invariant()
    {
        $tenantId = 4;
        $productId = 1;
        $date = date('Y-m-d');

        // Inicializar stock diario
        $stock = $this->stockManager->getOrInitializeDailyStock($tenantId, $productId, $date, 30);

        $this->assertEquals(30, $stock->initial_stock);
        $this->assertEquals(30, $stock->available_stock);
        $this->assertEquals(0, $stock->reserved_stock);
        $this->assertEquals(0, $stock->sold_stock);

        // Invariante: 30 == 30 + 0 + 0
        $this->stockManager->assertStockInvariant($stock);
    }

    /**
     * PR14-D & PR15: Reserva Transaccional y Transición de Estados
     */
    public function test_pr14_d_reserve_stock_and_release()
    {
        $tenantId = 4;
        $productId = 1;

        // Reservar 5 unidades
        $res = $this->stockManager->reserveStock($tenantId, $productId, 5);
        $this->assertTrue($res['success']);
        $this->assertNotNull($res['reservation_id']);

        $stockAfterRes = DB::table('daily_stocks')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('date', date('Y-m-d'))
            ->first();

        $this->assertEquals(25, $stockAfterRes->available_stock);
        $this->assertEquals(5, $stockAfterRes->reserved_stock);
        $this->stockManager->assertStockInvariant($stockAfterRes);

        // Cancelar la reserva -> debe regresar el stock a disponible
        $released = $this->stockManager->releaseReservation($tenantId, $res['reservation_id'], 'CANCELLED');
        $this->assertTrue($released);

        $stockAfterRelease = DB::table('daily_stocks')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('date', date('Y-m-d'))
            ->first();

        $this->assertEquals(30, $stockAfterRelease->available_stock);
        $this->assertEquals(0, $stockAfterRelease->reserved_stock);
        $this->stockManager->assertStockInvariant($stockAfterRelease);
    }

    /**
     * PR16-B: Reglas Core Deterministas Authoritative sobre ai_instructions
     */
    public function test_pr16_b_core_authoritative_rules()
    {
        $tenantId = 4;
        $productId = 1;

        // Configurar regla determinista Core en el producto
        DB::table('products')->where('id', $productId)->update([
            'requires_advance' => true,
            'advance_hours' => 24,
            'requires_deposit' => true,
            'deposit_percentage' => 50.00,
            'ai_instructions' => 'Instrucción libre contextual para la IA.'
        ]);

        $info = $this->infoTool->execute($tenantId, 'Dona Glaseada');

        $this->assertTrue($info['found']);
        $this->assertTrue($info['core_rules']['requires_advance']);
        $this->assertEquals(24, $info['core_rules']['advance_hours']);
        $this->assertEquals('AUTHORITATIVE_MANDATORY', $info['authority']['core_rules']);
    }

    /**
     * PR19-A & PR19-E: Verificación de Comprobante, Fingerprint Canónico y Detección de Reuso
     */
    public function test_pr19_voucher_matching_and_reused_detection()
    {
        $tenantId = 4;
        $orderId = 100;

        // Registrar cuenta de pago destino configurada
        DB::table('payment_destinations')->updateOrInsert(
            ['tenant_id' => $tenantId, 'provider' => 'YAPE'],
            ['holder_name' => 'Luis Fiestas', 'phone' => '977149110', 'is_active' => true]
        );

        $binaryVoucher1 = "VOUCHER_IMAGE_BINARY_BYTES_STREAM_1";
        $res1 = $this->voucherEngine->processVoucher(
            $tenantId,
            $orderId,
            $binaryVoucher1,
            'YAPE',
            38.00,
            'OP-893421',
            '977149110',
            'Luis Fiestas'
        );

        $this->assertEquals('MATCHED', $res1['status']);
        $this->assertEquals('PENDING_FINAL_VERIFICATION', $res1['recommendation']);

        // Test PR19-E: Intentar reutilizar el mismo número de operación en otro voucher recortado (diferente binario)
        $binaryVoucher2 = "VOUCHER_IMAGE_BINARY_BYTES_STREAM_2_CROPPED";
        $res2 = $this->voucherEngine->processVoucher(
            $tenantId,
            101,
            $binaryVoucher2,
            'YAPE',
            38.00,
            'OP-893421', // Mismo número de operación
            '977149110',
            'Luis Fiestas'
        );

        $this->assertEquals('REUSED', $res2['status']);
        $this->assertEquals('MANUAL_REVIEW', $res2['recommendation']);
    }
}
