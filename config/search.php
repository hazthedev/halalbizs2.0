<?php

return [

    // Semantic + visual search master switch. When off, the storefront falls
    // back to the existing Scout keyword search and the embedders are idle.
    'enabled' => env('SEARCH_SEMANTIC_ENABLED', true),

    // Text embedding driver: 'local' (deterministic, no network — dev/tests)
    // or 'remote' (a real embedding model in production).
    'driver' => env('SEARCH_EMBED_DRIVER', 'local'),

    // Local text-embedding dimensionality.
    'dimensions' => (int) env('SEARCH_EMBED_DIMS', 256),

    // Remote embedding endpoint (e.g. Voyage / OpenAI compatible). Inert until
    // a URL + key are supplied; failures degrade to the local embedder.
    'remote' => [
        'url' => env('SEARCH_EMBED_URL'),
        'key' => env('SEARCH_EMBED_KEY'),
        'model' => env('SEARCH_EMBED_MODEL', 'voyage-3'),
        'timeout' => (int) env('SEARCH_EMBED_TIMEOUT', 20),
    ],

];
