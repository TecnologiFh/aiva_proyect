<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración Global de AIVA Commerce SaaS Platform
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'GROQ'),
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        ],
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        ],
        'ollama' => [
            'url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
        ],
        'deepseek' => [
            'key' => env('DEEPSEEK_API_KEY'),
        ],
    ],

    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET', 'aiva_meta_app_secret_master_2026'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'aiva_secure_verify_token_2026'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],

    'gateway' => [
        'secret' => env('GATEWAY_SECRET', 'aiva_internal_gateway_secret_2026'),
    ],

];
