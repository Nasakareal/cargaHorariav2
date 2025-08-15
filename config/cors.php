<?php

return [
    // Aplica CORS solo al API
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Métodos permitidos
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // SOLO tus dominios (NO se pone la ruta /cargaHoraria aquí, solo esquema+host)
    'allowed_origins' => [
        'https://www.utmorelia.com',
        'https://utmorelia.com',
    ],

    // Si tuvieras subdominios, podrías usar patrones (si no, déjalo vacío)
    'allowed_origins_patterns' => [
        // '#^https://.*\.utmorelia\.com$#',
    ],

    // Encabezados permitidos
    'allowed_headers' => ['*'],

    // Headers expuestos al cliente (útil para descargas)
    'exposed_headers' => ['Content-Disposition'],

    // Cache del preflight
    'max_age' => 3600,

    // Tokens personales (Bearer) ⇒ false. Solo pon true si usaras cookies/sesión SPA.
    'supports_credentials' => false,
];
