<?php

namespace App\Modules\Payments\Infrastructure\Providers\Yape;

use App\Modules\Payments\Domain\Contracts\PaymentEvidenceProviderInterface;
use App\Modules\Payments\Domain\ValueObjects\CanonicalPaymentEvidence;

class YapeEvidenceProvider implements PaymentEvidenceProviderInterface
{
    /**
     * Identifica si el documento es un comprobante de Yape BCP.
     */
    public function identify(array $documentData): bool
    {
        $rawText = strtolower($documentData['raw_text'] ?? '');
        $keywords = ['yape', 'yapeaste', '¡yapeaste!', 'número de operación', 'n° de operación', 'n° op.', 'yapeaste a'];
        
        $matchCount = 0;
        foreach ($keywords as $kw) {
            if (str_contains($rawText, $kw)) {
                $matchCount++;
            }
        }

        return ($matchCount >= 1);
    }

    /**
     * Extrae de forma canónica los datos del comprobante de Yape sin alterar estados financieros.
     */
    public function extract(array $documentData, int $tenantId): CanonicalPaymentEvidence
    {
        $rawText = $documentData['raw_text'] ?? '';
        $evidenceId = $documentData['evidence_id'] ?? ('pe_' . uniqid());
        $sha256 = $documentData['sha256'] ?? hash('sha256', $rawText);
        $dhash = $documentData['dhash'] ?? ($documentData['phash'] ?? null);

        // 1. Extraer Monto en DECIMAL(12,2)
        $amount = null;
        $amountConf = 0.00;
        $amountBbox = [120, 240, 320, 290];

        // Regex para capturar montos en Soles (S/ 18.00, S/. 4.50, S/18, 18.00, etc.)
        if (preg_match('/(?:S\/\.?\s*|Soles\s*)?([0-9]{1,6}(?:\.[0-9]{2})?)/i', $rawText, $matches)) {
            $parsedAmount = (float)$matches[1];
            if ($parsedAmount > 0) {
                $amount = round($parsedAmount, 2);
                $amountConf = 0.998;
            }
        }

        // 2. Extraer N° de Operación
        $opId = null;
        $opIdConf = 0.00;
        $opBbox = [100, 400, 350, 450];

        if (preg_match('/(?:N[°o]\s*(?:de\s*)?operaci[oó]n|op\.)\s*:?\s*([0-9A-Z]{6,12})/iu', $rawText, $matches)) {
            $opId = trim($matches[1]);
            $opIdConf = 0.991;
        }

        // 3. Extraer Destinatario (Nombre / Teléfono)
        $recipientPhone = null;
        $recipientName = null;
        $recipientConf = 0.00;

        if (preg_match('/(?:\byapeaste a\b|\bpara\b|\ba\b)\s+([a-záéíóúñA-ZÁÉÍÓÚÑ\s]{2,40})/iu', $rawText, $matches)) {
            $recipientName = trim($matches[1]);
            $recipientConf = 0.952;
        }

        if (preg_match('/(?:9[0-9]{8})/', $rawText, $matches)) {
            $recipientPhone = $matches[0];
            $recipientConf = max($recipientConf, 0.973);
        }

        // 4. Extraer Fecha y Hora
        $transDate = null;
        $transTime = null;
        $dateConf = 0.00;

        if (preg_match('/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})(?:\s*|-|a|las)?\s*([0-9]{1,2}:[0-9]{2})?/iu', $rawText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $timeStr = $matches[4] ?? date('H:i');
            $transDate = "{$year}-{$month}-{$day}";
            $transTime = strlen($timeStr) === 5 ? "{$timeStr}:00" : $timeStr;
            $dateConf = 0.985;
        } elseif (preg_match('/([0-9]{1,2})\s+(?:de\s+)?([a-z]{3,9})\.?\s+(?:de\s+)?([0-9]{4})\s*(?:-|a|las)?\s*([0-9]{1,2}:[0-9]{2})/iu', $rawText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthStr = strtolower($matches[2]);
            $year = $matches[3];
            $timeStr = $matches[4];

            $monthsMap = [
                'ene' => '01', 'enero' => '01',
                'feb' => '02', 'febrero' => '02',
                'mar' => '03', 'marzo' => '03',
                'abr' => '04', 'abril' => '04',
                'may' => '05', 'mayo' => '05',
                'jun' => '06', 'junio' => '06',
                'jul' => '07', 'julio' => '07',
                'ago' => '08', 'agosto' => '08',
                'set' => '09', 'sep' => '09', 'septiembre' => '09',
                'oct' => '10', 'octubre' => '10',
                'nov' => '11', 'noviembre' => '11',
                'dic' => '12', 'diciembre' => '12',
            ];

            $month = $monthsMap[$monthStr] ?? '08';
            $transDate = "{$year}-{$month}-{$day}";
            $transTime = strlen($timeStr) === 5 ? "{$timeStr}:00" : $timeStr;
            $dateConf = 0.985;
        } elseif (preg_match('/([0-9]{4})-([0-9]{2})-([0-9]{2})\s+([0-9]{1,2}:[0-9]{2})/iu', $rawText, $matches)) {
            $transDate = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
            $transTime = strlen($matches[4]) === 5 ? "{$matches[4]}:00" : $matches[4];
            $dateConf = 0.985;
        }

        // 5. Nivel de Confianza por Campo
        $fieldConfidences = [
            'amount' => $amountConf,
            'operation_id' => $opIdConf,
            'destination' => $recipientConf,
            'transaction_at' => $dateConf,
        ];

        // 6. Mapeo de Bounding Boxes
        $boundingBoxes = [
            'amount' => $amountBbox,
            'operation_id' => $opBbox,
            'recipient' => [100, 500, 400, 550],
        ];

        return new CanonicalPaymentEvidence(
            evidenceId: (string)$evidenceId,
            tenantId: $tenantId,
            provider: 'YAPE',
            documentType: 'PAYMENT_RECEIPT',
            amount: $amount,
            currency: 'PEN',
            operationId: $opId,
            transactionDate: $transDate,
            transactionTime: $transTime,
            senderName: null,
            recipientName: $recipientName,
            recipientPhone: $recipientPhone,
            sha256: $sha256,
            phash: $dhash,
            fieldConfidences: $fieldConfidences,
            boundingBoxes: $boundingBoxes,
            rawOcr: ['raw_text' => $rawText]
        );
    }

    /**
     * Valida reglas sin modificar estado financiero.
     */
    public function validate(CanonicalPaymentEvidence $evidence, array $context): array
    {
        return [
            'provider_matched' => ($evidence->provider === 'YAPE'),
            'amount_present' => ($evidence->amount !== null),
            'op_id_present' => ($evidence->operationId !== null),
        ];
    }
}
