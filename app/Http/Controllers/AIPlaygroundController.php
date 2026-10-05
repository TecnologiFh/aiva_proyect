<?php

namespace App\Http\Controllers;

use App\Modules\Inventory\Services\DailyStockManager;
use App\Modules\Messaging\Tools\GetProductAvailabilityTool;
use App\Modules\Messaging\Tools\GetProductInfoTool;
use App\Modules\Tenant\Services\UserTenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AIPlaygroundController extends Controller
{
    protected DailyStockManager $stockManager;
    protected GetProductAvailabilityTool $availabilityTool;
    protected GetProductInfoTool $infoTool;
    protected UserTenantAccessService $tenantAccessService;

    public function __construct()
    {
        $this->stockManager = new DailyStockManager();
        $this->availabilityTool = new GetProductAvailabilityTool($this->stockManager);
        $this->infoTool = new GetProductInfoTool();
        $this->tenantAccessService = new UserTenantAccessService();
    }

    public function processMessageDirectly(int $tenantId, string $userMessage): array
    {
        $context = $this->getTenantContext($tenantId);
        $provider = strtoupper($this->getEnvValue('AI_PROVIDER') ?: 'GROQ');

        return [
            'response' => "Hola, gracias por consultar a {$context['name']}. Procesando su consulta sobre '{$userMessage}'.",
            'provider' => $provider,
            'status' => 'SUCCESS',
        ];
    }

    public function getEnvValue(string $key): string
    {
        $configKeyMap = [
            'AI_PROVIDER' => 'aiva.ai.provider',
            'GROQ_API_KEY' => 'aiva.ai.groq.key',
            'OPENAI_API_KEY' => 'aiva.ai.openai.key',
            'GEMINI_API_KEY' => 'aiva.ai.gemini.key',
            'OLLAMA_BASE_URL' => 'aiva.ai.ollama.url',
            'OLLAMA_MODEL' => 'aiva.ai.ollama.model',
            'DEEPSEEK_API_KEY' => 'aiva.ai.deepseek.key',
        ];

        if (isset($configKeyMap[$key]) && !empty(config($configKeyMap[$key]))) {
            return (string) config($configKeyMap[$key]);
        }

        $val = env($key);
        if (empty($val) && file_exists(base_path('.env'))) {
            $content = file_get_contents(base_path('.env'));
            if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $m)) {
                $val = trim($m[1]);
            }
        }
        return (string) $val;
    }

    private function getTenantContext(int $tenantId): array
    {
        $products = DB::table('products')->where('tenant_id', $tenantId)->where(function($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'ARCHIVED');
        })->get();
        $tenantInfo = DB::table('tenants')->where('id', $tenantId)->first();
        $dailyStocks = DB::table('daily_stocks')->where('tenant_id', $tenantId)->where('date', date('Y-m-d'))->get()->keyBy('product_id');

        $businessName = $tenantInfo->name ?? 'Donuts Fs Pastelería';
        $businessCategory = $tenantInfo->category ?? 'Gastronomía / Pastelería Artesanal';

        $catalogSummary = $products->map(function ($p) use ($dailyStocks) {
            $ds = $dailyStocks->get($p->id);
            $available = $ds ? (int)$ds->available_stock : (int)($p->stock ?? 10);
            $price = number_format((float)($p->base_price ?? $p->price ?? 0), 2);
            $reqAdv = !empty($p->requires_advance) ? " (Requiere {$p->advance_hours}h de anticipación)" : "";
            return "- {$p->name}: S/ {$price} | Disponible Hoy: {$available} unidades{$reqAdv}";
        })->implode("\n");

        return [
            'tenant_id' => $tenantId,
            'name' => $businessName,
            'category' => $businessCategory,
            'products' => $products,
            'catalog_summary' => $catalogSummary,
        ];
    }

    public function index()
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant();
        $context = $this->getTenantContext($tenantId);
        $provider = strtoupper($this->getEnvValue('AI_PROVIDER') ?: 'GROQ');
        $openAiKey = $this->getEnvValue('OPENAI_API_KEY');
        $geminiKey = $this->getEnvValue('GEMINI_API_KEY');
        $ollamaUrl = $this->getEnvValue('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434';
        $ollamaModel = $this->getEnvValue('OLLAMA_MODEL') ?: 'llama3.2';
        $deepseekKey = $this->getEnvValue('DEEPSEEK_API_KEY');
        $groqKey = $this->getEnvValue('GROQ_API_KEY');

        return view('ai_playground', [
            'products' => $context['products'],
            'tenantName' => $context['name'],
            'provider' => $provider,
            'openAiKey' => $openAiKey,
            'geminiKey' => $geminiKey,
            'ollamaUrl' => $ollamaUrl,
            'ollamaModel' => $ollamaModel,
            'deepseekKey' => $deepseekKey,
            'groqKey' => $groqKey,
            'hasOpenAIKey' => !empty($openAiKey),
            'hasGeminiKey' => !empty($geminiKey),
            'hasGroqKey' => !empty($groqKey),
        ]);
    }

    public function processMessage(Request $request)
    {
        $tenantId = $this->tenantAccessService->requireActiveTenant($request->input('tenant_id') ? (int)$request->input('tenant_id') : null);
        $userMessage = trim($request->input('message', ''));
        $history = $request->input('history', []);
        $startTime = microtime(true);

        if (empty($userMessage)) {
            return response()->json([
                'response' => 'Por favor escribe una consulta.',
                'reply_text' => 'Por favor escribe una consulta.',
                'provider' => 'AIVA Engine',
                'latency_ms' => 0
            ]);
        }

        $lowerMsg = mb_strtolower($userMessage);
        $triggerSound = false;
        $attentionCode = null;

        // DETECCIÓN DE HANDOVER A OPERADOR HUMANO
        if (str_contains($lowerMsg, 'asesor') || str_contains($lowerMsg, 'humano') || str_contains($lowerMsg, 'persona') || str_contains($lowerMsg, 'reclamo') || str_contains($lowerMsg, 'queja')) {
            $attentionCode = 'ATT-' . date('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $triggerSound = true;
            try {
                DB::table('handover_attentions')->insert([
                    'id' => (string) Str::uuid(),
                    'attention_code' => $attentionCode,
                    'tenant_id' => $tenantId,
                    'customer_phone' => '+51977149110',
                    'customer_name' => 'Cliente de Prueba',
                    'reason' => str_contains($lowerMsg, 'reclamo') ? 'COMPLAINT' : 'EXPLICIT_HUMAN_REQUEST',
                    'status' => 'CREATED',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {}
        }

        $ctx = $this->getTenantContext($tenantId);

        // SYSTEM PROMPT DINÁMICO DE CONVERSACIÓN NATURAL CON HISTORIAL DE CHAT
        $systemPrompt = "Eres AIVA, la Asistente Virtual Oficial de '{$ctx['name']}' ({$ctx['category']}).
Habla de forma muy amable, natural, comercial y fluida. Manten el hilo de la conversación.

ESTADO Y CATÁLOGO DE PRODUCTOS EN TIEMPO REAL:
{$ctx['catalog_summary']}

REGLAS OBLIGATORIAS:
1. Responde de forma personalizada tomando en cuenta el historial anterior de la conversación.
2. Si el cliente pregunta por donas, productos o precios, usa exactamente la información del catálogo en tiempo real.
3. Si el cliente solicita hablar con un asesor o humano, avísale amablemente que has notificado al equipo y generado su ticket de atención " . ($attentionCode ? "({$attentionCode})" : "") . ".
4. Mantén tus respuestas concisas y amigables (máximo 2 a 3 frases).";

        // CONSTRUIR HISTORIAL DE MENSAJES PARA LLM
        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach (array_slice($history, -6) as $h) {
            if (isset($h['role'], $h['content'])) {
                $messagesPayload[] = [
                    'role' => $h['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => (string)$h['content'],
                ];
            }
        }
        $messagesPayload[] = ['role' => 'user', 'content' => $userMessage];

        $provider = strtoupper($this->getEnvValue('AI_PROVIDER') ?: 'GROQ');
        $groqKey = $this->getEnvValue('GROQ_API_KEY');
        $openAiKey = $this->getEnvValue('OPENAI_API_KEY');
        $ollamaUrl = $this->getEnvValue('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434';
        $ollamaModel = $this->getEnvValue('OLLAMA_MODEL') ?: 'llama3.2';

        // 1. INTENTO GROQ CLOUD LPU (REAL LIVE LLM CON HISTORIAL)
        if (!empty($groqKey) && ($provider === 'GROQ' || empty($openAiKey))) {
            $groqModel = $this->getEnvValue('GROQ_MODEL') ?: 'openai/gpt-oss-120b';
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$groqKey}",
                    'Content-Type' => 'application/json',
                ])->timeout(6)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $groqModel,
                    'messages' => $messagesPayload,
                    'temperature' => 0.7,
                ]);

                if ($res->successful()) {
                    $aiReply = $res->json('choices.0.message.content');
                    if (!empty($aiReply)) {
                        return response()->json([
                            'response' => $aiReply,
                            'reply_text' => $aiReply,
                            'provider' => "Groq LPU ({$groqModel})",
                            'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                            'trigger_sound' => $triggerSound,
                            'attention_code' => $attentionCode,
                        ]);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. INTENTO OPENAI GPT-4O-MINI (REAL LIVE LLM CON HISTORIAL)
        if (!empty($openAiKey)) {
            try {
                $res = Http::withHeaders([
                    'Authorization' => "Bearer {$openAiKey}",
                    'Content-Type' => 'application/json',
                ])->timeout(6)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messagesPayload,
                    'temperature' => 0.7,
                ]);

                if ($res->successful()) {
                    $aiReply = $res->json('choices.0.message.content');
                    if (!empty($aiReply)) {
                        return response()->json([
                            'response' => $aiReply,
                            'reply_text' => $aiReply,
                            'provider' => 'OpenAI Real LLM (gpt-4o-mini)',
                            'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                            'trigger_sound' => $triggerSound,
                            'attention_code' => $attentionCode,
                        ]);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 3. INTENTO OLLAMA LOCAL (REAL LOCAL LLM)
        try {
            $res = Http::timeout(6)->post(rtrim($ollamaUrl, '/') . '/api/chat', [
                'model' => $ollamaModel,
                'messages' => $messagesPayload,
                'stream' => false,
            ]);

            if ($res->successful()) {
                $aiReply = $res->json('message.content');
                if (!empty($aiReply)) {
                    return response()->json([
                        'response' => $aiReply,
                        'reply_text' => $aiReply,
                        'provider' => "Ollama Local ({$ollamaModel})",
                        'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                        'trigger_sound' => $triggerSound,
                        'attention_code' => $attentionCode,
                    ]);
                }
            }
        } catch (\Throwable $e) {}

        // RESPUESTA INTELIGENTE DE FALLBACK SI NINGÚN LLM RESPONDE
        $fallbackReply = "¡Hola! En {$ctx['name']} tenemos disponibles nuestras Donas Glaseadas a S/ 4.50. ¿Deseas hacer un pedido o consultar algo más?";
        if ($triggerSound) {
            $fallbackReply = "🚨 Entendido. He generado tu Ticket de Atención Humana ({$attentionCode}) y notificado a nuestro equipo en el panel de operaciones. Un asesor se comunicará contigo de inmediato.";
        }

        return response()->json([
            'response' => $fallbackReply,
            'reply_text' => $fallbackReply,
            'provider' => 'AIVA Engine (Core Hybrid)',
            'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'trigger_sound' => $triggerSound,
            'attention_code' => $attentionCode,
        ]);
    }
}
