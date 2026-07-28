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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'wialon' => [
        'url' => env('WIALON_API_URL'),
        'token' => env('WIALON_API_TOKEN'),
        'token_fleet_2' => env('WIALON_API_TOKEN_FLEET2'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'fines' => [
        'url' => env('FINES_API_URL'),
        'tin' => env('FINES_TIN'),
        'referer' => env('FINES_REFERER'),
        'rpk' => env('FINES_RPK'),
        'user_agent' => env('FINES_USER_AGENT', config('app-custom.fines_user_agent')),
        'newrelic' => env('FINES_NEWRELIC'),
        'nls' => env('FINES_NLS'),
    ],

];
