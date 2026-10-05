<?php

namespace App\Modules\Messaging\Services;

class CaptionPolicy
{
    /**
     * Generar caption determinista y contextual para cualquier medio visual
     */
    public function generateCaption(
        string $assetTitle,
        ?string $targetLabel = null,
        bool $isMenu = true,
        bool $isGeneralMenu = false,
        string $tenantName = ''
    ): string {
        $cleanTarget = trim($targetLabel ?? '');

        if ($isGeneralMenu) {
            $suffix = !empty($tenantName) ? " {$tenantName}" : "";
            return "¡Claro! Aquí tienes nuestra Carta General{$suffix} 😊";
        }

        if (!empty($cleanTarget) && $isMenu) {
            $formattedTarget = mb_convert_case($cleanTarget, MB_CASE_TITLE, "UTF-8");
            return "¡Claro! Aquí tienes la carta donde están nuestras {$formattedTarget} 😊";
        }

        return "¡Claro! Aquí tienes nuestra {$assetTitle} 😊";
    }
}
