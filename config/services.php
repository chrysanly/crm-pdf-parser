<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | Resume Parsing Sidecar (Python / FastAPI)
    |--------------------------------------------------------------------------
    |
    | The only outbound integration: app/Services/Parsing/SidecarResumeParser
    | talks to the FastAPI service in sidecar/.
    |
    | The default is 'sidecar' ON PURPOSE: a missing/typo'd env var must fail
    | loudly (parse error, retryable) rather than silently serve FakeResumeParser's
    | sample document as if it came from the upload. 'fake' is opt-in — the test
    | suite pins it in phpunit.xml.
    |
    */

    'sidecar' => [
        'driver' => env('SIDECAR_DRIVER', 'sidecar'),
        'url' => env('SIDECAR_URL', 'http://127.0.0.1:8001'),
        'token' => env('SIDECAR_TOKEN', ''),
        'timeout' => (int) env('SIDECAR_TIMEOUT', 30),
    ],

];
