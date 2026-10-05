<?php

namespace App\Modules\Multimodal\Runtime\Enums;

/**
 * Tipos universales de evidencia visual comercial (v2.3).
 * No son mutuamente excluyentes; una misma imagen puede manifestar múltiples evidencias.
 */
enum CommercialVisualEvidenceType: string
{
    // Financiero / Pagos
    case PAYMENT_RECEIPT = 'PAYMENT_RECEIPT';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case POS_RECEIPT = 'POS_RECEIPT';
    case PAYMENT_QR = 'PAYMENT_QR';
    case PAYMENT_PROVIDER = 'PAYMENT_PROVIDER';
    case OPERATION_NUMBER = 'OPERATION_NUMBER';
    case AMOUNT = 'AMOUNT';
    case DATE = 'DATE';
    case TIME = 'TIME';
    case DESTINATION = 'DESTINATION';

    // Fiscal / Tributario
    case TAX_DOCUMENT = 'TAX_DOCUMENT';
    case INVOICE = 'INVOICE';
    case SALES_RECEIPT = 'SALES_RECEIPT';

    // Comercial / Menú / Catálogo / Productos
    case MENU_CARD = 'MENU_CARD';
    case MENU_SECTION = 'MENU_SECTION';
    case PRODUCT_IMAGE = 'PRODUCT_IMAGE';
    case PRODUCT_ITEM = 'PRODUCT_ITEM';
    case PRODUCT_PRICE = 'PRODUCT_PRICE';
    case PRICE_OBSERVATION = 'PRICE_OBSERVATION';
    case PRODUCT_VISUAL_REFERENCE = 'PRODUCT_VISUAL_REFERENCE';
    case CATALOG_ITEM = 'CATALOG_ITEM';

    // Metadatos y Clasificaciones Especiales
    case NON_COMMERCIAL_IMAGE = 'NON_COMMERCIAL_IMAGE';
    case AMBIGUOUS_VISUAL_EVIDENCE = 'AMBIGUOUS_VISUAL_EVIDENCE';
    case UNKNOWN_COMMERCIAL_EVIDENCE = 'UNKNOWN_COMMERCIAL_EVIDENCE';
}
