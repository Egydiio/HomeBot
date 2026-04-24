<?php

return [
    'default' => env('WHATSAPP_DRIVER', 'webjs'),

    'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN', ''),

    'drivers' => [
        'webjs' => [
            'url' => env('WHATSAPP_WEBJS_URL', 'http://whatsapp-service:3000'),
            'token' => env('WHATSAPP_SERVICE_TOKEN', ''),
            'timeout' => (int) env('WHATSAPP_WEBJS_TIMEOUT', 10),
        ],

        'zapi' => [
            'instance' => env('ZAPI_INSTANCE', ''),
            'token' => env('ZAPI_TOKEN', ''),
            'client_token' => env('ZAPI_CLIENT_TOKEN', ''),
        ],
    ],
];
