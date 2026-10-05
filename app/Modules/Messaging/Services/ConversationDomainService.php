<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConversationDomainService
{
    /**
     * Buscar o crear una conversación garantizando el aislamiento estricto por tenant_id.
     */
    public function findOrCreateConversation(int $tenantId, string $customerPhone, ?string $customerName = null): object
    {
        $phone = preg_replace('/[^0-9]/', '', $customerPhone);

        $conversation = DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->where('customer_phone', $phone)
            ->first();

        // Garantizar existencia del cliente en el módulo Customer 360 CRM
        try {
            $crmService = new \App\Modules\CRM\Services\Customer360Service();
            $crmService->getOrCreateCustomer($tenantId, $phone, $customerName ?? ('Cliente ' . substr($phone, -4)));
        } catch (\Throwable $e) {}

        if ($conversation) {
            return $conversation;
        }

        $id = DB::table('conversations')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'customer_phone' => $phone,
            'customer_name' => $customerName ?? ('Cliente ' . substr($phone, -4)),
            'status' => 'OPEN',
            'mode' => 'AI',
            'last_message_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('conversations')->where('id', $id)->first();
    }

    /**
     * Registrar un mensaje en la BD MySQL con metadata enriquecida de IA (Latencia, Tokens, Proveedor).
     */
    public function recordMessage(
        int $tenantId,
        int $conversationId,
        string $senderType,
        string $content,
        array $metadata = []
    ): object {
        // Verificar pertenencia de la conversación al tenant_id
        $conv = DB::table('conversations')
            ->where('id', $conversationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$conv) {
            throw new HttpException(403, "Conversación no pertenece al Tenant ID {$tenantId}.");
        }

        $mediaUrl = $metadata['media_url'] ?? null;
        $messageType = !empty($mediaUrl) ? 'IMAGE' : 'TEXT';

        $messageId = DB::table('messages')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'sender_type' => strtoupper($senderType), // CUSTOMER, AI, AGENT
            'message_type' => $messageType,
            'content' => $content,
            'media_url' => $mediaUrl,
            'status' => 'SENT',
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualizar fecha del último mensaje en la conversación
        DB::table('conversations')
            ->where('id', $conversationId)
            ->update([
                'last_message_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('messages')->where('id', $messageId)->first();
    }

    /**
     * Obtener las conversaciones aisladas por Tenant ID.
     */
    public function getConversationsForTenant(int $tenantId, int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->map(function ($c) {
                $lastMsg = DB::table('messages')
                    ->where('conversation_id', $c->id)
                    ->orderByDesc('id')
                    ->first();
                $c->last_message_text = $lastMsg ? $lastMsg->content : 'Sin mensajes';
                $c->last_message_metadata = $lastMsg && $lastMsg->metadata ? json_decode($lastMsg->metadata, true) : null;
                return $c;
            });
    }

    /**
     * Obtener el historial completo de mensajes verificando autorización de Tenant.
     */
    public function getMessagesForConversation(int $tenantId, int $conversationId): \Illuminate\Support\Collection
    {
        // Validar que la conversación pertenezca al tenant_id
        $conv = DB::table('conversations')
            ->where('id', $conversationId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$conv) {
            throw new HttpException(403, "Acceso no autorizado a la conversación {$conversationId}.");
        }

        return DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($m) {
                $m->metadata = $m->metadata ? json_decode($m->metadata, true) : null;
                return $m;
            });
    }
}
