<?php

return [
    'name' => 'Prompt Flow',

    'default_cli' => env('DEFAULT_CLI', 'opencode'),

    'ai' => [
        'provider' => env('AI_FLOW_PROVIDER', 'anthropic'),
        'model' => env('AI_FLOW_MODEL', 'claude-sonnet-4-6'),
    ],

    'channels' => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'enabled' => env('TELEGRAM_ENABLED', false),
        ],
        'whatsapp' => [
            'api_key' => env('WHATSAPP_API_KEY'),
            'enabled' => env('WHATSAPP_ENABLED', false),
        ],
    ],
];
