<?php

return [
    'auth' => [
        'issuer' => env('PORTAL_JWT_ISSUER', env('APP_URL')), // fallback to app URL
        'access_token_ttl' => (int) env('PORTAL_ACCESS_TOKEN_TTL', 900),
        'refresh_token_ttl' => (int) env('PORTAL_REFRESH_TOKEN_TTL', 1209600),
        'jwt_secret' => env('PORTAL_JWT_SECRET'),
        'algorithm' => 'HS256',
    ],
];
