<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LaraSentry Server Connection
    |--------------------------------------------------------------------------
    */
    'server_url' => env('LARASENTRY_SERVER_URL', env('LARASENTRY_URL', 'http://127.0.0.1:8080')),
    'url'        => env('LARASENTRY_URL', env('LARASENTRY_SERVER_URL', 'http://127.0.0.1:8080')),
    'project'    => env('LARASENTRY_PROJECT', null),
    'api_key'    => env('LARASENTRY_API_KEY', null),
    'timeout'    => env('LARASENTRY_TIMEOUT', 2), // секунды на HTTP запрос

    /*
    |--------------------------------------------------------------------------
    | App Metadata
    |--------------------------------------------------------------------------
    */
    'app_url'     => env('APP_URL', 'http://localhost'),
    'release'     => env('LARASENTRY_RELEASE', env('APP_VERSION', '1.0.0')),
    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration (Optional)
    |--------------------------------------------------------------------------
    | When specified, exceptions are dispatched to the specified Laravel Queue.
    | If null or empty, exceptions will be sent synchronously.
    */
    'queue'            => env('LARASENTRY_QUEUE', null),
    'queue_connection' => env('LARASENTRY_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Context Collection Toggles
    |--------------------------------------------------------------------------
    | Управление сбором расширенных метаданных.
    */
    'send_code_snippet' => env('LARASENTRY_SEND_CODE_SNIPPET', true),
    'snippet_lines'     => env('LARASENTRY_SNIPPET_LINES', 9),

    'collect_breadcrumbs' => env('LARASENTRY_COLLECT_BREADCRUMBS', true),
    'max_breadcrumbs'     => env('LARASENTRY_MAX_BREADCRUMBS', 30),

    'collect_route'       => env('LARASENTRY_COLLECT_ROUTE', true),
    'collect_request'     => env('LARASENTRY_COLLECT_REQUEST', true),
    'collect_queue'       => env('LARASENTRY_COLLECT_QUEUE', true),
    'collect_system'      => env('LARASENTRY_COLLECT_SYSTEM', true),

    'collect_context'   => env('LARASENTRY_COLLECT_CONTEXT', true),
    'max_context_bytes' => env('LARASENTRY_MAX_CONTEXT_BYTES', 16384),

    /*
    |--------------------------------------------------------------------------
    | PII & Data Scrubbing
    |--------------------------------------------------------------------------
    | Ключи, значения которых принудительно заменяются на [FILTERED].
    */
    'scrub_fields' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
        'authorization',
        'cookie',
        'card',
        'card_number',
        'cvv',
        'cvc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Body Limits
    |--------------------------------------------------------------------------
    | Максимальный размер тела запроса (в байтах) перед усечением (по умолчанию 32 KB).
    */
    'max_body_bytes' => 32768,

    /*
    |--------------------------------------------------------------------------
    | Custom Tags
    |--------------------------------------------------------------------------
    | Additional tags to send with every captured exception.
    */
    'tags' => [
        // 'server_type' => 'worker',
    ],
];
