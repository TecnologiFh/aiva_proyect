<?php

namespace App\Modules\Payments\Application\Services;

use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;
use App\Modules\Multimodal\Runtime\Enums\CommercialVisualEvidenceType;

/**
 * Servicio Adaptador de Clasificación Multimedia.
 * Actualizado conforme a Sección 40 (v2.3): Acepta evidencia estructurada de P3.12 sin dependencia obligatoria de caption.
 */
class MediaClassifierService
{
    public const NOT_PAYMENT_EVIDENCE = 'NOT_PAYMENT_EVIDENCE';
    public const POSSIBLE_PAYMENT_EVIDENCE = 'POSSIBLE_PAYMENT_EVIDENCE';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Clasificar Intake Multimedia antes de derivar al Pipeline Financiero o Comercial.
     * Soporta entrada legacy ($imageBase64, $rawText) o evidencia estructurada de P3.12 ($structuredEvidence).
     */
    public function classifyMedia(
        ?string $imageBase64, 
        ?string $rawText = '', 
        StructuredVisualEvidenceDTO|array|null $structuredEvidence = null
    ): array {
        // 1. Si se provee evidencia estructurada de P3.12 (Sección 40: Adaptación sin barrera de caption)
        if ($structuredEvidence !== null) {
            $isPayment = false;
            $isNonCommercial = false;

            if ($structuredEvidence instanceof StructuredVisualEvidenceDTO) {
                $isPayment = $structuredEvidence->hasEvidence(CommercialVisualEvidenceType::PAYMENT_RECEIPT)
                    || $structuredEvidence->hasEvidence(CommercialVisualEvidenceType::BANK_TRANSFER)
                    || $structuredEvidence->hasEvidence(CommercialVisualEvidenceType::POS_RECEIPT);
                $isNonCommercial = $structuredEvidence->assetClassification === CommercialVisualEvidenceType::NON_COMMERCIAL_IMAGE->value;
            } elseif (is_array($structuredEvidence)) {
                $types = $structuredEvidence['detected_evidence'] ?? [];
                $isPayment = in_array('PAYMENT_RECEIPT', $types, true) || in_array('BANK_TRANSFER', $types, true) || in_array('POS_RECEIPT', $types, true);
                $isNonCommercial = ($structuredEvidence['asset_classification'] ?? '') === 'NON_COMMERCIAL_IMAGE';
            }

            if ($isPayment) {
                return [
                    'classification' => self::POSSIBLE_PAYMENT_EVIDENCE,
                    'is_candidate' => true,
                    'confidence' => 0.98,
                    'reason' => 'STRUCTURED_P312_PAYMENT_EVIDENCE',
                    'whatsapp_guidance' => 'Evidencia de pago confirmada mediante percepción multimodal P3.12.'
                ];
            }

            if ($isNonCommercial) {
                return [
                    'classification' => self::NOT_PAYMENT_EVIDENCE,
                    'is_candidate' => false,
                    'confidence' => 0.95,
                    'reason' => 'STRUCTURED_P312_NON_COMMERCIAL',
                    'whatsapp_guidance' => 'Recibimos tu imagen sin contenido transaccional o financiero.'
                ];
            }
        }

        $decoded = '';
        if (empty($imageBase64) && empty($rawText)) {
            return [
                'classification' => self::NOT_PAYMENT_EVIDENCE,
                'is_candidate' => false,
                'confidence' => 1.0,
                'reason' => 'MEDIA_EMPTY',
                'whatsapp_guidance' => 'No se recibió ninguna imagen adjunta.'
            ];
        }

        // Validar integridad física de la imagen
        if (!empty($imageBase64)) {
            $cleanBase64 = preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64);
            $decoded = base64_decode($cleanBase64, true);
            if ($decoded === false || strlen($decoded) < 100) {
                return [
                    'classification' => self::UNKNOWN,
                    'is_candidate' => false,
                    'confidence' => 0.2,
                    'reason' => 'BLURRY_OR_CORRUPTED_IMAGE',
                    'whatsapp_guidance' => 'La imagen recibida no se pudo procesar correctamente (borrosa o corrupta). Por favor envía nuevamente la captura clara.'
                ];
            }
        }

        $textLower = mb_strtolower($rawText ?? '');

        // 2. Descalificación explícita ante texto NO financiero innegable (paisajes, memes, mascotas)
        $explicitNonFinancialPatterns = [
            '/\bcumpleaños\b/i', '/\bcumple\b/i', '/\bfiesta infantil\b/i', '/\bfoto de mascota\b/i',
            '/\bperro\b/i', '/\bgato\b/i', '/\bpaisaje\b/i', '/\bmeme\b/i'
        ];
        foreach ($explicitNonFinancialPatterns as $pattern) {
            if (preg_match($pattern, $textLower)) {
                return [
                    'classification' => self::NOT_PAYMENT_EVIDENCE,
                    'is_candidate' => false,
                    'confidence' => 0.95,
                    'reason' => 'EXPLICIT_NON_FINANCIAL_TEXT',
                    'whatsapp_guidance' => 'Recibimos tu imagen, pero no parece corresponder a un comprobante de pago de Yape o Plin.'
                ];
            }
        }

        // 3. Financial Evidence Score Engine
        $score = 0;
        if (str_contains($textLower, 'yapeaste') || str_contains($textLower, 'plineaste')) $score += 4;
        if (str_contains($textLower, 'n° de operación') || str_contains($textLower, 'operacion') || str_contains($textLower, 'operación')) $score += 3;
        if (str_contains($textLower, 'destino') || str_contains($textLower, 'titular')) $score += 3;
        if (str_contains($textLower, 'código de seguridad') || str_contains($textLower, 'codigo de seguridad')) $score += 2;
        if (preg_match('/s\/\s*[0-9]+(?:\.[0-9]{2})?/i', $textLower)) $score += 3;
        if (str_contains($textLower, 'yape') || str_contains($textLower, 'plin') || str_contains($textLower, 'bcp') || str_contains($textLower, 'bbva') || str_contains($textLower, 'interbank')) $score += 2;
        if (str_contains($textLower, 'ahí está') || str_contains($textLower, 'ahi esta') || str_contains($textLower, 'aquí está') || str_contains($textLower, 'listo') || str_contains($textLower, 'ya yapee') || str_contains($textLower, 'captura') || str_contains($textLower, 'adjunto')) $score += 2;

        // Si la imagen es binariamente válida (>=200 bytes) o tiene score positivo
        if ($score >= 3 || (!empty($imageBase64) && strlen($decoded) >= 200 && $score >= 2)) {
            return [
                'classification' => self::POSSIBLE_PAYMENT_EVIDENCE,
                'is_candidate' => true,
                'confidence' => min(0.99, 0.70 + ($score * 0.05)),
                'reason' => 'FINANCIAL_EVIDENCE_SCORE_MATCH',
                'evidence_score' => $score,
                'whatsapp_guidance' => 'Evaluando candidato a evidencia de pago vía Payment Pipeline.'
            ];
        }

        // Si la imagen es un comprobante limpio sin caption (V1)
        if (!empty($imageBase64) && strlen($decoded) >= 500) {
            return [
                'classification' => self::POSSIBLE_PAYMENT_EVIDENCE,
                'is_candidate' => true,
                'confidence' => 0.85,
                'reason' => 'VALID_IMAGE_PAYLOAD_CANDIDATE',
                'evidence_score' => 1,
                'whatsapp_guidance' => 'Imagen recibida sin caption; procesando mediante percepción multimodal P3.12.'
            ];
        }

        return [
            'classification' => self::UNKNOWN,
            'is_candidate' => false,
            'confidence' => 0.50,
            'reason' => 'AMBIGUOUS_MEDIA_INTAKE',
            'whatsapp_guidance' => 'No pudimos reconocer datos de transferencia en la imagen. Por favor asegúrate de enviar la foto clara del comprobante de Yape/Plin.'
        ];
    }
}
