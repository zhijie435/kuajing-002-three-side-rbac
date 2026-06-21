<?php

define('CORS_ALLOWED_ORIGIN', 'http://localhost:5173');
define('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
define('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Requested-With');
define('CORS_MAX_AGE', 86400);

function applyCorsHeaders()
{
    header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: ' . CORS_ALLOWED_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_ALLOWED_HEADERS);
    header('Access-Control-Max-Age: ' . CORS_MAX_AGE);
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
