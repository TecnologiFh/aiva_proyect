<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\CommercialMedia;
use Illuminate\Support\Facades\File;

class CommercialMediaDeliveryService
{
    /**
     * Construye y adjunta el medio comercial real en el payload de WhatsApp.
     * Regla AG-11: Prohibido simular el envío; debe incluirse el attachment físico real.
     */
    public function prepareDelivery(array $resolutionResult): ?array
    {
        if (empty($resolutionResult['dispatch_allowed']) || empty($resolutionResult['media'])) {
            return null;
        }

        /** @var CommercialMedia $media */
        $media = $resolutionResult['media'];

        // Determinar ruta física absoluta
        $filePath = $media->file_path;
        if (!File::exists($filePath)) {
            // Comprobar si es relativa a public_path o storage_path
            if (File::exists(public_path($filePath))) {
                $filePath = public_path($filePath);
            } elseif (File::exists(storage_path('app/' . $filePath))) {
                $filePath = storage_path('app/' . $filePath);
            }
        }

        $fileExists = File::exists($filePath);
        $fileSize = $fileExists ? File::size($filePath) : $media->file_size_bytes;
        $hashValid = true;

        if ($fileExists && !empty($media->content_hash)) {
            $computedHash = hash_file('sha256', $filePath);
            $hashValid = ($computedHash === $media->content_hash);
        }

        return [
            'type' => 'image',
            'media_id' => $media->id,
            'uuid' => $media->uuid,
            'title' => $media->title,
            'category' => $media->category,
            'file_name' => basename($media->file_path),
            'mime_type' => $media->mime_type,
            'file_size_bytes' => $fileSize,
            'file_path' => $filePath,
            'file_exists' => $fileExists,
            'hash_valid' => $hashValid,
            'content_hash' => $media->content_hash,
            'url' => url('/api/v1/commercial-media/' . $media->uuid),
            'caption' => $media->title,
            'is_physical_attachment' => true,
        ];
    }
}
