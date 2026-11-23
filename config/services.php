<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'receipt_printer' => [
        'enabled' => (bool) env('POS_PRINTER_ENABLED', false),
        'name' => env('POS_PRINTER_NAME', 'POS58'),
        'chars_per_line' => (int) env('POS_PRINTER_CHARS_PER_LINE', 32),
        'open_drawer' => (bool) env('POS_PRINTER_OPEN_DRAWER', false),

        'bridge_url' => env('POS_PRINT_BRIDGE_URL', 'http://127.0.0.1:8000/print-bridge'),
        'bridge_origins' => env('POS_PRINT_BRIDGE_ORIGINS', 'https://cctvexpresstacurong.com'),
    ],
];
