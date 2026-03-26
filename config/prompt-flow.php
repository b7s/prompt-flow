<?php

return [
    'name' => 'Prompt Flow',

    'external_url' => env('APP_EXTERNAL_URL', env('APP_URL')),

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

    'linear' => [
        'linear_finish_status' => env('LINEAR_MOVE_TO_WHEN_FINISH', 'done'),

        'api_key' => env('LINEAR_API_KEY'),
        'organization_id' => env('LINEAR_ORGANIZATION_ID'),
        'webhook_secret' => env('LINEAR_WEBHOOK_SECRET'),
        'telegram_chat_id' => env('LINEAR_TELEGRAM_CHAT_ID'),
        'enabled' => env('LINEAR_ENABLED', false),
    ],
];
