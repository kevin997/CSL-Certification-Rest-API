<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',  // Certification
        'http://localhost:3001',  // Sales Website
        'http://localhost:3002',  // Training Website
        'http://localhost:3003',  // Ambassador App
        'http://localhost:3004',  // System Admin
        'http://localhost:3005',  // Brands IDP
        'http://localhost:5173',  // Marketplace Web
        'http://127.0.0.1:3000',
        'https://getkursa.app',
        'https://getkursa.org',
        'https://getkursa.net',
        'https://getkursa.com',
        'https://getkursa.space',
        'https://www.getkursa.space',
    ],

    'allowed_origins_patterns' => [
        '#^https?://.*\.csl-brands\.com$#',
        '#^https?://.*\.getkursa\.app$#',
        '#^https?://.*\.getkursa\.com$#',
        '#^https?://.*\.getkursa\.org$#',
        '#^https?://.*\.getkursa\.net$#',
        '#^https?://.*\.getkursa\.space$#',
        '#^https?://getkursa\.space$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
