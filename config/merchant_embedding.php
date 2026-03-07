<?php

return [
    'enabled' => env('MERCHANT_EMBEDDING_ENABLED', true),

    'driver' => env('MERCHANT_EMBEDDING_DRIVER', 'local'), // local | openai

    'dimension' => (int) env('MERCHANT_EMBEDDING_DIMENSION', 64),

    'local' => [
        'ngram_size' => 2,
        'ngram_overlap' => true,
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimension_override' => null, // null = use model default
    ],

    'classification' => [
        'min_similarity' => (float) env('MERCHANT_EMBEDDING_MIN_SIMILARITY', 0.72),
        'max_candidates' => (int) env('MERCHANT_EMBEDDING_MAX_CANDIDATES', 5),
        'source_weight' => (float) env('MERCHANT_EMBEDDING_SOURCE_WEIGHT', 0.72), // trong source_weights của v3
    ],
];
