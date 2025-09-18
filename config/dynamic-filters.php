<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration options.
    |
    */
    'security' => [
        // Maximum number of filters allowed in a single request
        'max_filters' => 50,
        
        // Maximum depth for nested relationships
        'max_nesting_level' => 5,
        
        // Log filter queries (for debugging and auditing)
        'log_queries' => env('DYNAMIC_FILTERS_LOG_QUERIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Operators
    |--------------------------------------------------------------------------
    |
    | This array maps operator keys to their SQL operator equivalents.
    | You can add or modify operators as needed for your application.
    |
    */
    'operators' => [
        'eq'           => '=',
        'neq'          => '!=',
        'gt'           => '>',
        'gte'          => '>=',
        'lt'           => '<',
        'lte'          => '<=',
        'like'         => 'like',
        'not_like'     => 'not like',
        'ilike'        => 'ilike',
        'not_ilike'    => 'not ilike',
        'starts_with'  => 'like',
        'ends_with'    => 'like',
        'contains'     => 'like',
        'in'           => 'in',
        'not_in'       => 'not in',
        'between'      => 'between',
        'not_between'  => 'not between',
        'null'         => 'null',
        'not_null'     => 'not null',
        'date'         => 'date',
        'year'         => 'year',
        'month'        => 'month',
        'day'          => 'day',
        'time'         => 'time',
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Whitelist
    |--------------------------------------------------------------------------
    |
    | Define globally allowed filterable columns that can be used across all models.
    | This is a security measure to prevent filtering on unauthorized columns.
    | Can be overridden per model using the $filterable property or filterable() method.
    |
    */
    'global_whitelist' => [
        // 'id',
        // 'status',
        // 'created_at',
        // 'updated_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Configure the default pagination settings.
    |
    */
    'pagination' => [
        'per_page' => 10,
        'max_per_page' => 100,
        'page_name' => 'page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Filters
    |--------------------------------------------------------------------------
    |
    | Register your custom filter classes here. The key should be the filter name
    | and the value should be the fully qualified class name of your filter.
    |
    */
    'custom_filters' => [
        // 'active_users' => \App\Filters\ActiveUsersFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filter Presets
    |--------------------------------------------------------------------------
    |
    | Define common filter presets that can be reused across your application.
    | These can be referenced in your API requests using a special syntax.
    |
    */
    'presets' => [
        // 'active_users' => [
        //     'status' => 'active',
        //     'email_verified' => true,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default search behavior for your application.
    |
    */
    'search' => [
        // The request parameter name for search terms
        'param' => 'search',
        
        // Maximum search term length
        'max_length' => 255,
        
        // Minimum search term length
        'min_length' => 1,
        
        // Search behavior configuration
        'min_term_length' => 2,      // Minimum length of search terms
        'max_terms' => 5,            // Maximum number of search terms to process
        'mode' => 'or',              // Default search mode: 'and' or 'or'
        'case_sensitive' => false,   // Whether search should be case sensitive
        
        // Common words to ignore in search
        'blacklist' => [
            'the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'as', 'by', 'is', 'it', 'that', 'this', 'be', 'are',
            'was', 'were', 'will', 'would', 'can', 'could', 'should', 'has',
            'have', 'had', 'not', 'but', 'what', 'which', 'when', 'where',
            'who', 'whom', 'how', 'why', 'if', 'then', 'else', 'from', 'into',
            'about', 'after', 'before', 'between', 'under', 'over', 'above',
            'below', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under',
            'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where',
            'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most',
            'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same',
            'so', 'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don',
            'should', 'now', 'd', 'll', 'm', 'o', 're', 've', 'y', 'ain', 'aren',
            'couldn', 'didn', 'doesn', 'hadn', 'hasn', 'haven', 'isn', 'ma',
            'mightn', 'mustn', 'needn', 'shan', 'shouldn', 'wasn', 'weren',
            'won', 'wouldn'
        ],
        
        // Search mode configuration
        'modes' => [
            'or' => [
                'description' => 'Match any of the search terms (wider results)'
            ],
            'and' => [
                'description' => 'Match all search terms (narrower results)'
            ],
            'exact' => [
                'description' => 'Match the exact phrase (most specific)'
            ]
        ],
        
        // Advanced search options
        'advanced' => [
            'enable_wildcards' => true,  // Enable * and ? as wildcards
            'enable_fuzzy' => false,     // Enable fuzzy/approximate matching
            'fuzzy_distance' => 1,       // Maximum edit distance for fuzzy search
            'enable_ngram' => false,     // Enable n-gram tokenization
            'ngram_min' => 2,            // Minimum n-gram length
            'ngram_max' => 4,            // Maximum n-gram length
        ],
        
        // Performance settings
        'performance' => [
            'max_results' => 1000,        // Maximum number of results to return
            'timeout' => 30,              // Query timeout in seconds
            'cache_ttl' => 300,           // Cache results for X seconds (0 to disable)
        ],
        
        // Default search operator (like, ilike, =, etc.)
        'operator' => 'like',
        
        // Whether to use full-text search if available
        'use_fulltext' => false,
        
        // Full-text search mode (natural, boolean, etc.)
        'fulltext_mode' => 'natural',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sorting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default sorting behavior for your application.
    |
    */
    'sort' => [
        // Default sorting field and direction
        'default_field' => 'created_at',
        'default_direction' => 'desc',
        
        // Whether to allow sorting by any column (false to only allow sorting by whitelisted columns)
        'allow_any_column' => false,
        
        // Default sort parameter name in the request
        'param' => 'sort',
        
        // Direction indicators
        'direction_indicators' => [
            '+' => 'asc',
            '-' => 'desc'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    |
    | Enable debugging features for development.
    |
    */
    'debug' => [
        // Enable to log all filter operations
        'log_queries' => env('FILTER_DEBUG', false),
        
        // Parameter to enable debug mode via request
        'param' => 'debug_filters',
    ],
];
