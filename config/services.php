<?php

return [

    'wppconnect' => [
        'url' => env('WPP_CONNECT_URL'),
        'session' => env('WPP_CONNECT_SESSION', 'alugapro'),
        'secret_key' => env('WPP_CONNECT_SECRET_KEY'),
        'connect_timeout' => (int) env('WPP_CONNECT_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('WPP_CONNECT_TIMEOUT', 30),
    ],

    'meter_ocr' => [
        'url' => env('METER_OCR_URL', 'http://ocr:8000'),
        'timeout' => (int) env('METER_OCR_TIMEOUT', 30),
        'min_confidence' => (float) env('OCR_MIN_CONFIDENCE', 0.70),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),
        'url' => env('OPENAI_RESPONSES_URL', 'https://api.openai.com/v1/responses'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
