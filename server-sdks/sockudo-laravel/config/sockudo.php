<?php

declare(strict_types=1);

$scheme = env('SOCKUDO_SCHEME', 'http');

return [
    /*
    | Laravel's default broadcaster when config/broadcasting.php has not been
    | published yet. Existing application configuration always wins.
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Native SDK connection
    |--------------------------------------------------------------------------
    |
    | The facade and SockudoManager use this named broadcasting connection.
    | Laravel events use it when BROADCAST_CONNECTION=sockudo.
    |
    */
    'connection' => env('SOCKUDO_CONNECTION', 'sockudo'),

    /*
    |--------------------------------------------------------------------------
    | Default Sockudo broadcasting connection
    |--------------------------------------------------------------------------
    |
    | The service provider adds this connection at runtime when the application
    | has not defined broadcasting.connections.sockudo itself.
    |
    */
    'broadcasting' => [
        'driver' => 'sockudo',
        'key' => env('SOCKUDO_APP_KEY'),
        'secret' => env('SOCKUDO_APP_SECRET'),
        'app_id' => env('SOCKUDO_APP_ID'),
        'options' => [
            'host' => env('SOCKUDO_HOST', '127.0.0.1'),
            'port' => (int) env('SOCKUDO_PORT', $scheme === 'https' ? 443 : 6001),
            'scheme' => $scheme,
            'useTLS' => $scheme === 'https',
            'path' => env('SOCKUDO_PATH', ''),
            'timeout' => (int) env('SOCKUDO_TIMEOUT', 30),
            'encryption_master_key_base64' => env('SOCKUDO_ENCRYPTION_MASTER_KEY_BASE64', ''),
        ],
        'client_options' => [
            'connect_timeout' => (float) env('SOCKUDO_CONNECT_TIMEOUT', 5),
        ],
    ],
];
