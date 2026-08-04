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

    'tmdb' => [
        'key'        => env('TMDB_API_KEY'),
        'base_url'   => 'https://api.themoviedb.org/3',
        'image_base' => 'https://image.tmdb.org/t/p/w500',
    ],

    'hyperbeam' => [
        'key'             => env('HYPERBEAM_API_KEY'),
        'base_url'        => 'https://engine.hyperbeam.com/v0',
        'region'          => env('HYPERBEAM_REGION', 'NA'),               // NA | EU | AS (NA is nearest to BR)
        'start_url'       => env('HYPERBEAM_START_URL', 'about:blank'),
        'offline_timeout' => (int) env('HYPERBEAM_OFFLINE_TIMEOUT', 300), // idle seconds -> VM auto-closes
        'width'           => (int) env('HYPERBEAM_WIDTH', 1280),          // base resolution (÷4, >=540)
        'height'          => (int) env('HYPERBEAM_HEIGHT', 720),
        'fps'             => (int) env('HYPERBEAM_FPS', 30),              // base smoothness (24-60)
    ],

];
