<?php

return [
    'wsdl' => env('ULTRA_WSDL', 'https://portal.it-ultra.com/b2b/ru/ws/b2b.1cws?wsdl'),

    'options' => [
        'trace' => true,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'authentication' => SOAP_AUTHENTICATION_BASIC,
        'login' => env('ULTRA_WSDL_USERNAME'),
        'password' => env('ULTRA_WSDL_PASSWORD'),
    ],

    'output_path' => env('ULTRA_OUTPUT_PATH', storage_path('app/ultra/catalog.xml')),

    'product_url_template' => env('ULTRA_PRODUCT_URL', 'https://example.com/product/{code}'),

    'poll' => [
        'max_attempts' => env('ULTRA_POLL_ATTEMPTS', 15),
        'sleep_seconds' => env('ULTRA_POLL_SLEEP', 4),
    ],
];
