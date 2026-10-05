<?php

use App\Modules\Messaging\Controllers\WhatsAppWebhookController;
use App\Shared\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

// Endpoint público GET de verificación de Meta Cloud API
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);

// Endpoint POST de recepción de eventos con resolución de contexto multi-tenant
Route::middleware([ResolveTenantContext::class])->group(function () {
    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
});
