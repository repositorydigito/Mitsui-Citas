<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Agent IA Credentials
    |--------------------------------------------------------------------------
    |
    | Credenciales para generar tokens de autenticación
    |
    */
    'client_id' => env('API_CLIENT_ID', 'agent-ia-mitsui'),
    'client_secret' => env('API_CLIENT_SECRET', 'change-in-production'),

    /*
    |--------------------------------------------------------------------------
    | Agent IA Token
    |--------------------------------------------------------------------------
    |
    | Token de autenticación para el agente de IA conversacional
    | Este token se debe incluir en el header Authorization como Bearer token
    |
    */
    'agent_token' => env('API_AGENT_TOKEN', 'default-dev-token-change-in-production'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuración de rate limiting para la API
    |
    */
    'rate_limit' => [
        'max_attempts' => env('API_RATE_LIMIT_MAX', 60),
        'decay_minutes' => env('API_RATE_LIMIT_DECAY', 1),
    ],
];
