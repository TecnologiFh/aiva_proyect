<?php

namespace App\Modules\Multimodal\Runtime\Contracts;

use App\Modules\Multimodal\Runtime\DTOs\StructuredVisualEvidenceDTO;

interface VisualEvidenceRouterContract
{
    /**
     * Resuelve y despacha la evidencia estructurada hacia el adaptador de dominio autorizado.
     *
     * @return array<string, mixed>
     */
    public function routeAndReconcile(StructuredVisualEvidenceDTO $evidence, array $context = []): array;
}
