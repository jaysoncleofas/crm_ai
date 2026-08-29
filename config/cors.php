<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The SPA is served from the same origin as the API in production, so CORS
    | only really matters for the Vite dev server. Origins are always explicit:
    | a wildcard cannot be combined with credentialed (cookie) requests.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:8089,http://127.0.0.1:8089,http://localhost:5173,http://127.0.0.1:5173'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept', 'Authorization', 'Content-Type', 'X-Requested-With',
        'X-XSRF-TOKEN', 'X-CSRF-TOKEN', 'Origin',
    ],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Remaining', 'X-RateLimit-Limit'],

    'max_age' => 3600,

    'supports_credentials' => true,

];
