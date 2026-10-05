<?php

namespace App\Modules\Multimodal\Runtime\Contracts;

use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;

interface VisualEvidenceAdapterContract
{
    /**
     * Determina si este adaptador puede procesar la evidencia provista.
     */
    public function supports(StructuredVisualEvidenceDTO $evidence): bool;

    /**
     * Procesa y reconcilia la evidencia visual contra el dominio autorizado propietario.
     * 
     * Regla Constitucional:
     * El adaptador jamás muta directamente tablas comerciales o financieras;
     * delega a la autoridad propietaria de dominio (P2.11, AG-46, etc.).
     *
     * @return array<string, mixed> Resultado canónico de la reconciliación
     */
    public function reconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array;
}
