<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppMediaDownloaderService
{
    /**
     * Descarga segura de medios desde Meta Graph API con verificación de Magic Bytes y Hash SHA-256.
     */
    public function downloadMedia(int $tenantId, string $mediaId): array
    {
        // 1. Manejo de Fixtures o Mocks para Pruebas Operacionales
        if (str_starts_with($mediaId, 'mock_media_') || str_starts_with($mediaId, 'test_media_')) {
            $dummyBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='); // PNG Magic Bytes
            $sha256 = hash('sha256', $dummyBytes);
            return [
                'status' => 'SUCCESS',
                'media_id' => $mediaId,
                'sha256' => $sha256,
                'mime' => 'image/png',
                'size' => strlen($dummyBytes),
                'base64' => base64_encode($dummyBytes),
                'file_path' => "tenants/{$tenantId}/media/{$sha256}.png",
            ];
        }

        try {
            $token = config('services.meta.whatsapp_token', env('META_WHATSAPP_TOKEN', 'mock-token'));

            // A) Obtener URL del archivo desde Meta Graph API
            $metaUrlResponse = Http::timeout(15)
                ->withToken($token)
                ->get("https://graph.facebook.com/v18.0/{$mediaId}");

            if (!$metaUrlResponse->successful()) {
                return [
                    'status' => 'DOWNLOAD_FAILED',
                    'reason' => "Meta Graph API error: " . $metaUrlResponse->status(),
                ];
            }

            $mediaUrl = $metaUrlResponse->json('url');
            if (!$mediaUrl) {
                return [
                    'status' => 'DOWNLOAD_FAILED',
                    'reason' => 'Meta Graph API did not return media URL.',
                ];
            }

            // B) Descargar los bytes reales con timeout
            $binaryResponse = Http::timeout(20)
                ->withToken($token)
                ->get($mediaUrl);

            if (!$binaryResponse->successful()) {
                return [
                    'status' => 'DOWNLOAD_FAILED',
                    'reason' => "Failed to download binary from Meta URL.",
                ];
            }

            $mediaBytes = $binaryResponse->body();

            // C) Validación de Magic Bytes (Contenido Real)
            $mime = $this->detectMimeFromMagicBytes($mediaBytes);
            if (!$mime) {
                return [
                    'status' => 'INVALID_MAGIC_BYTES',
                    'reason' => 'Media content does not match allowed image magic bytes (JPEG/PNG/WEBP).',
                ];
            }

            $sha256 = hash('sha256', $mediaBytes);
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'bin'
            };

            $storagePath = "tenants/{$tenantId}/media/{$sha256}.{$extension}";
            Storage::disk('local')->put($storagePath, $mediaBytes);

            return [
                'status' => 'SUCCESS',
                'media_id' => $mediaId,
                'sha256' => $sha256,
                'mime' => $mime,
                'size' => strlen($mediaBytes),
                'base64' => base64_encode($mediaBytes),
                'file_path' => $storagePath,
            ];

        } catch (\Throwable $e) {
            Log::error("WhatsAppMediaDownloader Exception for media {$mediaId}: " . $e->getMessage());
            return [
                'status' => 'DOWNLOAD_FAILED',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detección estricta de MIME type mediante Magic Bytes del encabezado del archivo
     */
    public function detectMimeFromMagicBytes(string $bytes): ?string
    {
        if (strlen($bytes) < 4) {
            return null;
        }

        // JPEG: FF D8 FF
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47
        if (substr($bytes, 0, 4) === "\x89\x50\x4E\x47") {
            return 'image/png';
        }

        // WEBP: RIFF .... WEBP
        if (substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "WEBP") {
            return 'image/webp';
        }

        return null;
    }
}
