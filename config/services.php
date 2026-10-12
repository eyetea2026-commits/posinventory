<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    /*
    | Direct ESC/POS printing to a 58mm thermal receipt printer (Officom
    | OC-58IIH) shared in Windows. Only works when this Laravel server runs
    | on the same PC as the printer, so it's OFF unless the local .env turns
    | it on; with it off, the POS keeps using the browser receipt.
    */
    'receipt_printer' => [
        'enabled' => (bool) env('POS_PRINTER_ENABLED', false),
        'name' => env('POS_PRINTER_NAME', 'POS58'),
        'chars_per_line' => (int) env('POS_PRINTER_CHARS_PER_LINE', 32),
        'open_drawer' => (bool) env('POS_PRINTER_OPEN_DRAWER', false),

        // Print bridge: the deployed site sends receipt lines to the local
        // copy of this app on the cashier PC (`php artisan serve`), which
        // prints them straight to the thermal printer. bridge_url is used by
        // the deployed site's pages (empty turns it off); bridge_origins is
        // checked by the local copy (comma-separated sites allowed to print).
        'bridge_url' => env('POS_PRINT_BRIDGE_URL', 'http://127.0.0.1:8000/print-bridge'),
        'bridge_origins' => env('POS_PRINT_BRIDGE_ORIGINS', 'https://cctvexpresstacurong.com'),
    ],

];
