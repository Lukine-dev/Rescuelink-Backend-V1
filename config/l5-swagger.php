<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'RESCUELINK API',
                'version' => '1.0.0',
            ],
            'routes' => [
                'api' => 'api/documentation',
                'docs' => 'api-docs.json',
            ],
            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [
                    base_path('app/OpenApi'),  // Scan OpenApi directory first
                    base_path('app/Http/Controllers/Api'),
                ],
                'excludes' => [],
                'base' => env('L5_SWAGGER_BASE_PATH', '/api/v1'),
            ],
            'securityDefinitions' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ],
    ],
    'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true), // Set to true for debugging
];