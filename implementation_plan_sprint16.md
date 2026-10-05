# Arquitectura e Plan de Implementación v16.0 - Sprint 16: AI Vision Engine (Procesamiento e Interpretación Inteligente de Vouchers, Facturas & Fotos de Productos)

## Resumen Ejecutivo

El **Sprint 16** construye el **AI Vision Engine**, otorgando a **AIVA** la capacidad de "ver" e interpretar cualquier imagen comercial recibida por WhatsApp o cargada en la consola: vouchers de pago (**Yape**, **Plin**, transferencias bancarias), comprobantes físicos, facturas, fotografías de productos y códigos QR.

Verifica automáticamente el número de operación, fecha, titular y monto transferido, contrastándolo con el pedido en el **Payments Engine** (Sprint 7) para **aprobar el pago en 0 segundos y prevenir fraudes por capturas reutilizadas o adulteradas**.

---

## 1. Flujo de Arquitectura del AI Vision Engine

```text
Imagen/Voucher enviado por WhatsApp (Sprint 5)
                          │
                          ▼
            AnalyzeVoucherAction (AI Vision Engine)
                          │
                          ├─► OCR & Extracción de Entidades (Extrae Monto, N° Operación, Titular)
                          ├─► Control Anti-Fraude (Verifica que el N° Operación no fue usado previamente)
                          └─► Matching con Payments Engine (Sprint 7)
                                    │
                                    ├───► SI COINCIDE: Emite PaymentApproved Event (Sprint 7)
                                    │     ├─► Genera Boleta Electrónica en SUNAT (Sprint 15)
                                    │     ├─► Descuenta Stock en Kardex (Sprint 8)
                                    │     └─► Responde por WhatsApp: "✅ ¡Pago de S/ 81.00 Verificado!"
                                    │
                                    └───► SI NO COINCIDE: Notifica al cliente/operador
```

---

## 2. Componentes Clave del AI Vision Engine

### 2.1 Enums del Dominio de Visión por IA
- **`VisionDocumentType`**: `YAPE_VOUCHER`, `PLIN_VOUCHER`, `BANK_TRANSFER_VOUCHER`, `INVOICE`, `PRODUCT_PHOTO`, `QR_CODE`.
- **`VisionValidationStatus`**: `VALIDATED`, `AMOUNT_MISMATCH`, `EXPIRED_VOUCHER`, `DUPLICATE_OPERATION`, `UNREADABLE`.

### 2.2 Servicio de Interpretación Visual (`AIVisionEngineService`)
- Extrae metadatos estructurados de la imagen.
- Inyecta confianza (`confidence_score`) y valida el voucher contra la base de datos de pagos.

---

## 3. Esquema de Base de Datos (Sprint 16 v16.0)

### 3.1 Tabla `ai_vision_analyses` (Historial Auditado de Análisis de Imágenes)
```sql
CREATE TABLE ai_vision_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid BINARY(16) NOT NULL UNIQUE,
    tenant_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    document_type VARCHAR(50) NOT NULL, -- YAPE_VOUCHER, PLIN_VOUCHER, PRODUCT_PHOTO...
    image_url TEXT NOT NULL,
    extracted_data JSON NOT NULL, -- amount, operation_number, recipient, timestamp
    confidence_score FLOAT NOT NULL DEFAULT 1.0,
    validation_status VARCHAR(50) NOT NULL DEFAULT 'VALIDATED', -- VALIDATED, DUPLICATE_OPERATION...
    processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    INDEX idx_vision_operation (tenant_id, document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Estructura Modular (`app/Modules/Vision/`)

```text
AIVA/
├── app/
│   ├── Core/
│   │   └── Enums/
│   │       ├── VisionDocumentType.php
│   │       └── VisionValidationStatus.php
│   │
│   ├── Modules/
│   │   └── Vision/
│   │       ├── Application/
│   │       │   ├── AnalyzeVoucher/
│   │       │   │   ├── AnalyzeVoucherAction.php
│   │       │   │   ├── AnalyzeVoucherInput.php
│   │       │   │   └── AnalyzeVoucherResult.php
│   │       │   └── RecognizeProductFromPhoto/
│   │       │       ├── RecognizeProductFromPhotoAction.php
│   │       │       └── RecognizeProductFromPhotoInput.php
│   │       ├── Controllers/
│   │       │   └── VisionController.php
│   │       ├── Database/
│   │       │   └── Migrations/
│   │       │       └── 2026_08_06_000043_create_ai_vision_tables.php
│   │       ├── Models/
│   │       │   └── AIVisionAnalysis.php
│   │       ├── Repositories/
│   │       │   └── AIVisionAnalysisRepository.php
│   │       ├── Services/
│   │       │   └── AIVisionEngineService.php
│   │       └── Routes/api.php
```

---

## 5. Plan de Ejecución Inmediato

### Paso 1: Enums & Migración (`Vision Module`)
- Enums: `VisionDocumentType`, `VisionValidationStatus`.
- Migración `2026_08_06_000043_create_ai_vision_tables.php`.
- Modelo Eloquent: `AIVisionAnalysis`.

### Paso 2: Repositorio & Servicio de Visión IA (`Services/`)
- `AIVisionAnalysisRepository`.
- `AIVisionEngineService`: Interpretación OCR, verificación anti-duplicidad de operaciones de Yape/Plin y emparejamiento con pagos pendientes.

### Paso 3: Casos de Uso (Actions de la Capa `Application/`)
- **`AnalyzeVoucherAction`**: Recibe la imagen de un voucher, extrae datos, valida que no sea duplicado y aprueba el pago si el monto es correcto.
- **`RecognizeProductFromPhotoAction`**: Identifica productos en fotografías enviadas por clientes y busca coincidencias en el inventario.

### Paso 4: Controladores, Rutas & RBAC
- `VisionController`:
  - `POST /api/v1/vision/analyze-voucher` (Carga y procesamiento de voucher Yape/Plin).
  - `POST /api/v1/vision/search-product` (Búsqueda por foto de producto en catálogo).
- Protegidos mediante `RequirePermission` middleware (`crm:edit`).

### Paso 5: Suite de Pruebas Automatizadas
- `VoucherOCRParserTest`: Prueba de extracción de monto, n° de operación y titular de captura Yape/Plin.
- `AntiFraudDuplicateCheckTest`: Detección anti-fraude de operaciones duplicadas.
- `ProductRecognitionTest`: Emparejamiento por foto de producto con el catálogo.

---

## User Review Required

> [!IMPORTANT]
> **Prevención Anti-Fraude**: Al procesar un voucher de Yape/Plin, el sistema verifica que el número de operación no haya sido reutilizado previamente en ningún tenant ni pedido previo, previniendo fraudes por capturas recicladas. Por favor revisa la propuesta antes de proceder a la ejecución.

---

## Verification Plan

### Automated Tests
1. **`VoucherOCRParserTest`**: Extraer entidades de captura Yape.
2. **`AntiFraudDuplicateCheckTest`**: Bloquear operaciones reutilizadas.
3. **`ProductRecognitionTest`**: Búsqueda por foto.

### Manual Verification
1. Petición HTTP a `/api/v1/vision/analyze-voucher` procesando captura de prueba.
