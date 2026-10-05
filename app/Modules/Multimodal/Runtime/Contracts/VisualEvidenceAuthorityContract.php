<?php

namespace App\Modules\Multimodal\Runtime\Contracts;

interface VisualEvidenceAuthorityContract
{
    /**
     * Retorna el identificador del dominio propietario (ej: 'AG-46', 'P2.11', 'TAX_AUTHORITY').
     */
    public function getAuthorityDomain(): string;

    /**
     * Valida si la evidencia puede ser promovida a decisión comercial o financiera.
     */
    public function validateAuthority(array $evidencePayload): bool;
}
