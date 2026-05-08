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

    // Path al ejecutable de Python para correr scripts/parse_replay.py.
    // En dev (Windows) suele ser 'python' si está en PATH. En producción
    // (Linux) suele ser 'python3' o un path absoluto a un venv.
    'python' => [
        'bin' => env('PYTHON_BIN', 'python'),
    ],

    // Steam Web API key. Opcional — si no se configura, los users quedan
    // sin persona_name/avatar (se muestra el SteamID truncado).
    // Conseguir una en https://steamcommunity.com/dev/apikey (requiere
    // dominio asociado y cuenta Steam logueada).
    'steam' => [
        'api_key' => env('STEAM_API_KEY'),
    ],

];
