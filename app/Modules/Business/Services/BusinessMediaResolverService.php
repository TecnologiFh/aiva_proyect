<?php

namespace App\Modules\Business\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessMediaResolverService
{
    /**
     * MIMEs de imagen y documento permitidos.
     */
    protected array $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf'
    ];

    /**
     * Resolver recurso multimedia canónico para la carta o menú de un Tenant.
     */
    public function resolveMenuMedia(int $tenantId, ?string $messageQuery = null, ?string $conversationId = null): ?array
    {
        // 0. Delegación Multimodal P3.12 (MediaResolutionEngine con Ranking Lexicográfico y Memoria Anafórica)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('media_assets')) {
                $hasP312 = \App\Modules\Business\Models\MediaAsset::where('tenant_id', $tenantId)->where('status', 'ACTIVE')->exists();
                if ($hasP312) {
                    $p312Engine = new \App\Modules\Business\Services\MediaResolutionEngine();
                    $resP312 = $p312Engine->resolve($tenantId, $messageQuery ?? 'carta', $conversationId);
                    if ($resP312 && ($resP312['status'] ?? '') === 'MATCH_EXACT' && !empty($resP312['dispatch_allowed'])) {
                        $asset = $resP312['media_asset'];
                        $baseUrl = rtrim(config('app.url', 'http://127.0.0.1:8000'), '/');
                        if (str_starts_with($baseUrl, 'http://localhost') && !str_contains($baseUrl, ':8000')) {
                            $baseUrl = 'http://127.0.0.1:8000';
                        }
                        $cleanName = basename($resP312['file_path']);
                        $resolvedPath = public_path("cards/{$cleanName}");
                        if ((!file_exists($resolvedPath) || filesize($resolvedPath) === 0) && file_exists($resP312['file_path']) && filesize($resP312['file_path']) > 0) {
                            $resolvedPath = $resP312['file_path'];
                        }

                        $sizeBytes = (file_exists($resolvedPath) && !is_dir($resolvedPath)) ? @filesize($resolvedPath) : 0;
                        $mimeType = (file_exists($resolvedPath) && !is_dir($resolvedPath)) ? (@mime_content_type($resolvedPath) ?: 'image/png') : 'image/png';
                        $sha256 = $asset->sha256 ?: ((file_exists($resolvedPath) && $sizeBytes > 0) ? @hash_file('sha256', $resolvedPath) : null);

                        return [
                            'type' => 'image',
                            'media_id' => $resP312['media_id'],
                            'commercial_media_id' => $resP312['commercial_media_id'],
                            'purpose' => 'MENU',
                            'caption' => $resP312['caption'],
                            'display_url' => "{$baseUrl}/cards/{$cleanName}",
                            'file_path' => $resolvedPath,
                            'content_hash' => $sha256,
                            'sha256' => $sha256,
                            'size_bytes' => $sizeBytes,
                            'mime_type' => $mimeType,
                            'match_type' => 'MATCH_EXACT',
                            'resolution_type' => $resP312['resolution_type'],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("P3.12 delegation notice: " . $e->getMessage());
        }

        // 1. Delegación Canónica P3.11 (CommercialMediaResolverService)
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('commercial_media')) {
                $hasP311 = \App\Modules\Business\Models\CommercialMedia::where('tenant_id', $tenantId)->where('status', 'ACTIVE')->exists();
                if ($hasP311) {
                    $p311 = new \App\Modules\Business\Services\CommercialMediaResolverService();
                    $resP311 = $p311->resolve($tenantId, $messageQuery ?? 'carta', $conversationId);
                    if ($resP311 && $resP311['match_type'] === 'MATCH_EXACT' && !empty($resP311['dispatch_allowed'])) {
                        $deliveryService = new \App\Modules\Business\Services\CommercialMediaDeliveryService();
                        $payload = $deliveryService->prepareDelivery($resP311);
                        if ($payload) {
                            return [
                                'type' => 'image',
                                'media_id' => $payload['media_id'],
                                'commercial_media_id' => $payload['media_id'],
                                'purpose' => 'MENU',
                                'caption' => $payload['caption'],
                                'display_url' => $payload['url'],
                                'file_path' => $payload['file_path'],
                                'content_hash' => $payload['content_hash'],
                                'match_type' => 'MATCH_EXACT',
                            ];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("P3.11 delegation notice: " . $e->getMessage());
        }

        return $this->resolveMediaByPurpose($tenantId, 'MENU', $messageQuery);
    }

    /**
     * Resolver recurso multimedia canónico según su propósito o intención.
     */
    public function resolveMediaByPurpose(int $tenantId, string $purpose, ?string $messageQuery = null): ?array
    {
        // 1. Consulta SQL filtrando estrictamente por tenant_id del contexto
        $sources = DB::table('business_sources')
            ->where('tenant_id', $tenantId)
            ->whereIn('source_type', ['IMAGE_MENU', 'DOCUMENT_PDF', 'IMAGE', 'PDF', 'EXCEL_CSV'])
            ->where('status', 'ACTIVE_RESOURCE')
            ->orderBy('id', 'asc')
            ->get();

        if ($sources->isEmpty()) {
            return null;
        }

        $selectedSource = null;

        if (!empty($messageQuery) && $sources->count() > 1) {
            $queryNorm = mb_strtolower(strtr($messageQuery, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                'ñ' => 'n', 'ü' => 'u'
            ]));

            $scores = [];
            foreach ($sources as $idx => $src) {
                $score = 0;
                $content = mb_strtolower(strtr(($src->title ?? '') . ' ' . ($src->raw_content ?? '') . ' ' . ($src->file_path ?? ''), [
                    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                    'ñ' => 'n', 'ü' => 'u'
                ]));

                // Categoría 1: Pollos a la brasa / Brasa / Mostritos / Promos
                if (preg_match('/\b(pollo|pollos|brasa|mostrito|mostro|promocion|promociones|promo)\b/i', $queryNorm)) {
                    if (str_contains($content, 'pollo') || str_contains($content, 'brasa') || str_contains($content, 'carta1') || str_contains($content, 'mostrito')) {
                        $score += 30;
                    }
                }

                // Categoría 2: Chancho al cilindro / Chaufas / Dúos
                if (preg_match('/\b(chancho|chanchito|cilindro|chaufa|chaufas|duo|duos)\b/i', $queryNorm)) {
                    if (str_contains($content, 'chancho') || str_contains($content, 'chaufa') || str_contains($content, 'carta2') || str_contains($content, 'duo')) {
                        $score += 30;
                    }
                }

                // Categoría 3: Alitas / Salchipapas / Box / Criollos / Saltados
                if (preg_match('/\b(alita|alitas|salchipapa|salchipapas|salchimix|criollo|saltado|lomo saltado|piqueo|box)\b/i', $queryNorm)) {
                    if (str_contains($content, 'alita') || str_contains($content, 'salchipapa') || str_contains($content, 'criollo') || str_contains($content, 'carta3')) {
                        $score += 30;
                    }
                }

                // Categoría 4: Parrillas / Pastas / Guarniciones / Anticuchos
                if (preg_match('/\b(parrilla|parrillas|anticucho|anticuchos|chuleta|bistec|churrasco|pasta|pastas|fetuccini|guarnicion|guarniciones)\b/i', $queryNorm)) {
                    if (str_contains($content, 'parrilla') || str_contains($content, 'pasta') || str_contains($content, 'guarnicion') || str_contains($content, 'carta4')) {
                        $score += 30;
                    }
                }

                // Palabras individuales de la consulta
                $tokens = preg_split('/[\s,\.\?\!]+/', $queryNorm);
                foreach ($tokens as $token) {
                    if (strlen($token) >= 4 && str_contains($content, $token)) {
                        $score += 5;
                    }
                }

                $scores[$idx] = $score;
            }

            arsort($scores);
            $bestIdx = key($scores);
            if ($scores[$bestIdx] > 0) {
                $selectedSource = $sources[$bestIdx];
            }
        }

        // Si no se especificó categoría o ninguna puntuó alto, tomar la CARTA 1 (Principal)
        if (!$selectedSource) {
            $selectedSource = $sources->first(); // Primer recurso registrado en orden ascendente (Carta 1)
        }

        // 2. Doble Validación de Aislamiento Multi-Tenant (Explicit Tenant Enforcement)
        if (isset($selectedSource->tenant_id) && $selectedSource->tenant_id !== null && (int)$selectedSource->tenant_id !== $tenantId) {
            Log::warning("SECURITY ALERT: Intent de acceso cruzado a media_id de Tenant {$selectedSource->tenant_id} rechazado para Tenant {$tenantId}");
            return null;
        }

        return $this->buildCanonicalAttachment($selectedSource, $tenantId, $purpose);
    }

    /**
     * Resolver recurso por su ID canónico inmutable (media_id).
     */
    public function resolveMediaById(int $tenantId, string $mediaId): ?array
    {
        // Extraer ID numérico si el formato es src-{tenant_id}-{id}
        $rawId = $mediaId;
        if (preg_match('/^src-(\d+)-(\d+)$/', $mediaId, $matches)) {
            $ownerTenantId = (int)$matches[1];
            $rawId = (int)$matches[2];

            // Doble Validación: Denegar si el tenant del ID no coincide con el contexto
            if ($ownerTenantId !== $tenantId) {
                Log::warning("SECURITY ALERT: media_id '{$mediaId}' pertenece a Tenant {$ownerTenantId}, no a Tenant {$tenantId}");
                return null;
            }
        }

        $source = DB::table('business_sources')
            ->where('id', $rawId)
            ->where('status', 'ACTIVE_RESOURCE')
            ->first();

        if (!$source) {
            $source = DB::table('business_sources')
                ->where('tenant_id', $tenantId)
                ->whereIn('source_type', ['IMAGE_MENU', 'DOCUMENT_PDF', 'IMAGE', 'PDF', 'EXCEL_CSV'])
                ->where('status', 'ACTIVE_RESOURCE')
                ->orderByDesc('id')
                ->first();
        }

        if (!$source) {
            return null;
        }

        $actualTenantId = (isset($source->tenant_id) && $source->tenant_id !== null) ? (int)$source->tenant_id : $tenantId;

        return $this->buildCanonicalAttachment($source, $actualTenantId, 'RESOURCE');
    }

    /**
     * Construir el DTO del adjunto canónico garantizando existencia física y MIME permitido.
     */
    protected function buildCanonicalAttachment(object $source, int $tenantId, string $purpose): ?array
    {
        $canonicalMediaId = "src-{$tenantId}-{$source->id}";
        $rawPath = $source->file_path ?? $source->external_url ?? '';
        
        if (str_contains($rawPath, '/uploads/')) {
            $filePath = 'uploads/' . ltrim(substr($rawPath, strpos($rawPath, '/uploads/') + 9), '/');
        } else {
            $filePath = ltrim($rawPath, '/');
        }
        
        $physicalPath = public_path($filePath);

        // 3. Verificación de seguridad de Path Traversal
        if (!empty($filePath) && (str_contains($filePath, '..') || str_contains($filePath, "\0"))) {
            Log::error("SECURITY REJECT: Path traversal detectado en file_path: {$filePath}");
            return null;
        }

        // 4. Verificación física de existencia del archivo en disco
        if (!empty($filePath) && !file_exists($physicalPath)) {
            Log::warning("FILE MISSING: El archivo físico '{$physicalPath}' no existe en disco.");
            // Si tiene URL externa, continuar; si no, fallar de forma controlada
            if (empty($source->external_url)) {
                return null;
            }
        }

        // 5. Validación de tipo MIME
        $mimeType = 'image/jpeg';
        if (!empty($filePath) && file_exists($physicalPath)) {
            $ext = strtolower(pathinfo($physicalPath, PATHINFO_EXTENSION));
            $mimeType = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => 'image/jpeg',
            };
        }

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            Log::error("MIME REJECT: Tipo MIME '{$mimeType}' no permitido.");
            return null;
        }

        $type = ($mimeType === 'application/pdf') ? 'document' : 'image';
        $caption = !empty($source->title) ? $source->title : "Carta Oficial de Negocio";

        return [
            'type' => $type,
            'media_id' => $canonicalMediaId,
            'purpose' => $purpose,
            'caption' => $caption,
            'display_url' => url("/api/v1/media/{$canonicalMediaId}"),
            'storage_path' => $filePath,
            'mime_type' => $mimeType
        ];
    }
}
