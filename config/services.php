<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services Configuration
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-1.5-flash'),
    ],

];
