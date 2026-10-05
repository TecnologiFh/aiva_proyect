<?php

namespace App\Modules\Business\Controllers;

use App\Modules\Business\Services\BusinessMediaResolverService;
use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;

class MediaResolverController extends Controller
{
    protected BusinessMediaResolverService $mediaResolver;
    protected UserTenantAccessService $tenantAccess;

    public function __construct()
    {
        $this->mediaResolver = new BusinessMediaResolverService();
        $this->tenantAccess = new UserTenantAccessService();
    }

    /**
     * Endpoint resolutor seguro para transmitir binarios multimedia (/api/v1/media/{mediaId})
     */
    public function streamMedia(Request $request, string $mediaId)
    {
        $tenantId = null;

        if (preg_match('/^src-(\d+)-(\d+)$/', $mediaId, $matches)) {
            $tenantId = (int)$matches[1];
        }

        if (!$tenantId) {
            $tenantId = $this->tenantAccess->getActiveTenantId();
        }

        if (!$tenantId) {
            return response()->json(['error' => 'Acceso no autorizado al recurso multimedia.'], 403);
        }

        // Resolver el recurso multimedia canónico con aislamiento de Tenant
        $attachment = $this->mediaResolver->resolveMediaById($tenantId, $mediaId);

        if (!$attachment) {
            return response()->json(['error' => 'Recurso multimedia no encontrado o denegado.'], 404);
        }

        $physicalPath = public_path($attachment['storage_path']);

        if (!file_exists($physicalPath)) {
            return response()->json(['error' => 'Archivo físico no disponible en el servidor.'], 404);
        }

        // Transmitir los bytes del binario directamente
        return response()->file($physicalPath, [
            'Content-Type' => $attachment['mime_type'],
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="' . basename($physicalPath) . '"'
        ]);
    }
}
