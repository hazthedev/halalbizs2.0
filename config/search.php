<?php

return [

    // Semantic + visual search master switch. When off, the storefront falls
    // back to the existing Scout keyword search and the embedders are idle.
    'enabled' => env('SEARCH_SEMANTIC_ENABLED', true),

    // Text embedding driver: 'local' (deterministic, no network — dev/tests)
    // or 'remote' (a real embedding model in production).
    'driver' => env('SEARCH_EMBED_DRIVER', 'local'),

    // Local text-embedding dimensionality — i.e. how many hash buckets the
    // LocalHashEmbedder spreads tokens across. Raised 256 -> 4096 on 2026-08-10
    // after measuring the real 166-product catalogue: at 256, a SINGLE-WORD
    // query returned a top hit that did not even contain the word 42.45% of the
    // time (`pedas` -> Natural Mineral Water, `kari` -> Rice Pasta). Full
    // product names were already fine at 256 (0% wrong), which is why this hid
    // — the failure is concentrated in short queries, which is how people
    // actually search, and worst in the Malay half of the catalogue.
    //
    //   dims   wrong top hit (single word)   decode+dot per search
    //   256    42.45%                        5.5ms
    //   512    23.58%                        9.6ms
    //   1024   14.47%                        14.8ms
    //   4096    5.35%                        50.8ms
    //
    // ⚠ CHANGING THIS INVALIDATES EVERY STORED VECTOR. Run `php artisan
    // search:embed` immediately after deploying a change. VectorSearchService
    // now excludes non-matching dimensions rather than silently comparing a
    // truncated prefix, so a stale index makes smart search return nothing and
    // log a warning — visible, not wrong.
    //
    // ponytail: 4096 is where a dense PHP-side scan stops being free. Ranking
    // loads every live vector and dots it in process, so cost is O(products ×
    // dimensions) — fine at 266 products, untenable in the thousands. Past that
    // this needs a real vector index (pgvector / a search engine), not a bigger
    // number here.
    'dimensions' => (int) env('SEARCH_EMBED_DIMS', 4096),

    // Remote embedding endpoint (e.g. Voyage / OpenAI compatible). Inert until
    // a URL + key are supplied; failures degrade to the local embedder.
    'remote' => [
        'url' => env('SEARCH_EMBED_URL'),
        'key' => env('SEARCH_EMBED_KEY'),
        'model' => env('SEARCH_EMBED_MODEL', 'voyage-3'),
        'timeout' => (int) env('SEARCH_EMBED_TIMEOUT', 20),
    ],

];
