<?php

namespace App\Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentVoucherEngine
{
    /**
     * Normalización canónica de número telefónico (ej: +51 977 149 110 -> 977149110)
     */
    public function normalizePhone(?string $phone): string
    {
        if (empty($phone)) return '';
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '51') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Normalización canónica del monto a 2 decimales (ej: 38.0 -> 38.00)
     */
    public function normalizeAmount(float $amount): string
    {
        return number_format(abs($amount), 2, '.', '');
    }

    /**
     * Generar la Firma Lógica Canónica de Operación:
     * PAYMENT_OPERATION_FINGERPRINT = sha256(tenant_id | provider | op_number | amount | dest_phone)
     */
    public function generateOperationFingerprint(int $tenantId, string $provider, string $operationNumber, float $amount, string $destinationPhone): string
    {
        $normProvider = strtoupper(trim($provider));
        $normOpNumber = trim($operationNumber);
        $normAmount = $this->normalizeAmount($amount);
        $normPhone = $this->normalizePhone($destinationPhone);

        $canonicalString = "{$tenantId}|{$normProvider}|{$normOpNumber}|{$normAmount}|{$normPhone}";
        return hash('sha256', $canonicalString);
    }

    /**
     * Procesar y verificar un comprobante de pago recibido.
     */
    public function processVoucher(
        int $tenantId,
        int $orderId,
        string $imageBinary,
        string $provider,
        float $extractedAmount,
        ?string $extractedOpNumber,
        ?string $extractedPhone,
        ?string $extractedHolder
    ): array {
        // Nivel 1: Huella Digital del Archivo Binario
        $voucherHash = hash('sha256', $imageBinary);

        // Verificar si exactamente el mismo archivo binario ya fue enviado previamente
        $existingHash = DB::table('payment_vouchers')
            ->where('tenant_id', $tenantId)
            ->where('voucher_hash', $voucherHash)
            ->first();

        if ($existingHash) {
            return [
                'status' => 'REUSED',
                'recommendation' => 'MANUAL_REVIEW',
                'message' => '⚠️ El archivo del comprobante ya fue subido previamente para otro intento.'
            ];
        }

        // Tratamiento de operation_number Ausente: La ausencia de dato JAMÁS se interpreta como duplicación automática
        if (empty($extractedOpNumber)) {
            $fingerprint = hash('sha256', "{$tenantId}|{$provider}|NO_OP_NUM|" . rand(100000, 999999));
            
            DB::table('payment_vouchers')->insert([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'voucher_hash' => $voucherHash,
                'operation_fingerprint' => $fingerprint,
                'extracted_amount' => $extractedAmount,
                'extracted_operation_number' => null,
                'extracted_destination_phone' => $extractedPhone,
                'extracted_destination_holder' => $extractedHolder,
                'status' => 'MANUAL_REVIEW',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status' => 'MANUAL_REVIEW',
                'recommendation' => 'MANUAL_REVIEW',
                'message' => '⚠️ No se pudo extraer el número de operación del comprobante. Derivado a revisión manual.'
            ];
        }

        // Nivel 2: Firma Lógica Canónica de Operación
        $fingerprint = $this->generateOperationFingerprint($tenantId, $provider, $extractedOpNumber, $extractedAmount, $extractedPhone ?? '');

        // Detección de comprobante reutilizado con distinta imagen (recorte/modificación)
        $existingFingerprint = DB::table('payment_vouchers')
            ->where('tenant_id', $tenantId)
            ->where('operation_fingerprint', $fingerprint)
            ->first();

        if ($existingFingerprint) {
            DB::table('payment_vouchers')->insert([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'voucher_hash' => $voucherHash,
                'operation_fingerprint' => $fingerprint,
                'extracted_amount' => $extractedAmount,
                'extracted_operation_number' => $extractedOpNumber,
                'extracted_destination_phone' => $extractedPhone,
                'extracted_destination_holder' => $extractedHolder,
                'status' => 'REUSED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status' => 'REUSED',
                'recommendation' => 'MANUAL_REVIEW',
                'message' => "🚨 ALERTA: El número de operación N° {$extractedOpNumber} ya fue utilizado en el pedido #{$existingFingerprint->order_id}."
            ];
        }

        return DB::transaction(function() use ($tenantId, $orderId, $voucherHash, $fingerprint, $extractedAmount, $extractedOpNumber, $extractedPhone, $extractedHolder, $provider) {
            // Obtener cuentas configuradas del negocio en /settings/payments
            $configAccount = DB::table('payment_destinations')
                ->where('tenant_id', $tenantId)
                ->where('provider', strtoupper($provider))
                ->where('is_active', true)
                ->first();

            // Obtener el pedido
            $order = DB::table('orders')->where('tenant_id', $tenantId)->where('id', $orderId)->first();
            $expectedAmount = (float) ($order->total_amount ?? 0.00);

            $amountMatched = abs($extractedAmount - $expectedAmount) < 0.01;
            $phoneMatched = true;

            if ($configAccount && !empty($configAccount->phone) && !empty($extractedPhone)) {
                $phoneMatched = $this->normalizePhone($configAccount->phone) === $this->normalizePhone($extractedPhone);
            }

            $isMatched = $amountMatched && $phoneMatched;
            $finalStatus = $isMatched ? 'MATCHED' : 'MISMATCH';

            DB::table('payment_vouchers')->insert([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'voucher_hash' => $voucherHash,
                'operation_fingerprint' => $fingerprint,
                'extracted_amount' => $extractedAmount,
                'extracted_operation_number' => $extractedOpNumber,
                'extracted_destination_phone' => $extractedPhone,
                'extracted_destination_holder' => $extractedHolder,
                'status' => $finalStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'status' => $finalStatus,
                'recommendation' => $isMatched ? 'PENDING_FINAL_VERIFICATION' : 'MANUAL_REVIEW',
                'amount_matched' => $amountMatched,
                'phone_matched' => $phoneMatched,
                'message' => $isMatched
                    ? "🟢 Comprobante verificado con éxito (MATCHED). Coincide monto S/ {$extractedAmount} y destinatario."
                    : "⚠️ Discrepancia detectada en el comprobante. Monto o cuenta destino no coincide."
            ];
        });
    }
}
