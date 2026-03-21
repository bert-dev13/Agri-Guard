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

    'openweathermap' => [
        'key' => env('OPENWEATHERMAP_API_KEY'),
    ],

    'togetherai' => [
        'api_key' => env('TOGETHER_API_KEY'),
        'model' => env('TOGETHER_MODEL', 'openai/gpt-oss-20b'),
        'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.xyz/v1'),
        'fallback_models' => array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', (string) env('TOGETHER_FALLBACK_MODELS', 'openai/gpt-oss-20b'))
        ))),
    ],

];
