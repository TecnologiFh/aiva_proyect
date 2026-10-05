<?php

namespace App\Modules\Automation\Actions;

use Illuminate\Support\Facades\Http;

class SendWebhookAction implements AutomationActionInterface
{
    public function execute(array $params, array $context): bool
    {
        $targetUrl = $params['target_url'] ?? null;
        if (!$targetUrl) {
            return false;
        }

        try {
            Http::timeout(5)->post($targetUrl, [
                'event_context' => $context,
                'executed_at' => now()->toIso8601String(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'SEND_WEBHOOK';
    }
}
