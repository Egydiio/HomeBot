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

    'paddleocr' => [
        'endpoint' => env('PADDLEOCR_ENDPOINT', 'http://paddleocr:8866'),
    ],

    'google_vision' => [
        'key_path' => env('GOOGLE_VISION_KEY_PATH', ''),
        'feature' => env('GOOGLE_VISION_FEATURE', 'document_text_detection'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
    ],

    'ocr' => [
        'min_image_bytes' => (int) env('OCR_MIN_IMAGE_BYTES', 4096),
        'max_image_bytes' => (int) env('OCR_MAX_IMAGE_BYTES', 10485760),
        'result_cache_ttl_seconds' => (int) env('OCR_RESULT_CACHE_TTL_SECONDS', 259200),
    ],

    'llama' => [
        'endpoint' => env('LLAMA_ENDPOINT', 'http://localhost:4891'),
    ],

];
