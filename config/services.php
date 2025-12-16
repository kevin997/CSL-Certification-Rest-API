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
        'token' => env('POSTMARK_TOKEN', '2cc2bfdb-b6a5-4543-8466-3adfcf063045'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailjet' => [
        'key' => env('MAILJET_APIKEY'),
        'secret' => env('MAILJET_APISECRET'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', default: 'AAGW4ZsUYSxeny5LbNFDW7rOmiVdKuVbnWA'),
        'chat_id'   => env('TELEGRAM_CHAT_ID', default: "-1001836815830"),
    ],

    'media_service' => [
        'url' => env('MEDIA_SERVICE_URL'),
        'secret' => env('MEDIA_SERVICE_SECRET'),
    ],

    'livekit' => [
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'server_url' => env('LIVEKIT_SERVER_URL', 'ws://localhost:7880'),
    ],

    'ipgeolocation' => [
        'api_key' => env('IPGEOLOCATION_API_KEY', 'f632e06f3f6a489299101706b1a2fa32'),
    ],

];
