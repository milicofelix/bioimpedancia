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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-6.1-terra'),
        'vision_model' => env('OPENAI_VISION_MODEL', env('OPENAI_MODEL', 'gpt-6.1-terra')),
        // Explicit trial setting, independently configurable for agents and OCR.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'medium'),
        'vision_reasoning_effort' => env('OPENAI_VISION_REASONING_EFFORT', env('OPENAI_REASONING_EFFORT', 'medium')),
        'assistant_max_output_tokens' => (int) env('OPENAI_ASSISTANT_MAX_OUTPUT_TOKENS', 6000),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'bodyscan_ocr_timeout' => (int) env('OPENAI_BODYSCAN_OCR_TIMEOUT', 90),
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
