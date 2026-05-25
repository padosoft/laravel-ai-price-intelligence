<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Override any table name if it clashes with the host application.
    */
    'tables' => [
        'tenants' => 'pi_tenants',
        'products' => 'pi_products',
        'monitoring_targets' => 'pi_monitoring_targets',
        'competitor_sources' => 'pi_competitor_sources',
        'competitor_products' => 'pi_competitor_products',
        'match_proposals' => 'pi_match_proposals',
        'price_observations' => 'pi_price_observations',
        'price_daily_aggregates' => 'pi_price_daily_aggregates',
        'content_snapshots' => 'pi_content_snapshots',
        'stock_observations' => 'pi_stock_observations',
        'promo_observations' => 'pi_promo_observations',
        'fetch_logs' => 'pi_fetch_logs',
        'forecasts' => 'pi_forecasts',
        'anomalies' => 'pi_anomalies',
        'narratives' => 'pi_narratives',
        'assortment_gaps' => 'pi_assortment_gaps',
        'content_gaps' => 'pi_content_gaps',
        'review_insights' => 'pi_review_insights',
        'ai_decision_logs' => 'pi_ai_decision_logs',
        'repricing_rules' => 'pi_repricing_rules',
        'rule_decisions' => 'pi_rule_decisions',
        'alerts' => 'pi_alerts',
        'webhook_subscriptions' => 'pi_webhook_subscriptions',
        'api_keys' => 'pi_api_keys',
    ],

    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    | mode: "single" (tenant_id column, default) | "database" (stancl/tenancy).
    */
    'tenancy' => [
        'mode' => env('PI_TENANCY_MODE', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery (AI search for competitor URLs)
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        'providers_priority' => ['brave', 'tavily', 'firecrawl'],
        'cache' => ['enabled' => true, 'ttl' => 86400],
        'monthly_budget_guard' => null,
        'ai_search_cooldown_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scraping
    |--------------------------------------------------------------------------
    */
    'scraping' => [
        'default_driver' => env('PI_SCRAPE_DRIVER', 'auto'), // search_provider|browsershot|auto
        'browsershot' => ['cluster_nodes' => []],
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace adapters
    |--------------------------------------------------------------------------
    */
    'marketplaces' => [
        'amazon' => [
            'driver' => env('PI_AMAZON_DRIVER', 'auto'), // auto|sp_api|keepa|scrape
            'rate_limit_rpm' => (int) env('PI_AMAZON_RPM', 20),
            'sp_api' => [
                'client_id' => env('PI_AMAZON_SPAPI_CLIENT_ID'),
                'client_secret' => env('PI_AMAZON_SPAPI_CLIENT_SECRET'),
                'refresh_token' => env('PI_AMAZON_SPAPI_REFRESH_TOKEN'),
                'endpoint' => env('PI_AMAZON_SPAPI_ENDPOINT', 'https://sellingpartnerapi-eu.amazon.com'),
                'token_endpoint' => env('PI_AMAZON_SPAPI_TOKEN_ENDPOINT', 'https://api.amazon.com/auth/o2/token'),
                'marketplace_id' => env('PI_AMAZON_MARKETPLACE_ID', 'APJ6JRA9NG5V4'), // amazon.it
            ],
            'keepa' => [
                'key' => env('PI_KEEPA_KEY'),
                'domain' => (int) env('PI_KEEPA_DOMAIN', 8), // 8 = amazon.it
                'endpoint' => env('PI_KEEPA_ENDPOINT', 'https://api.keepa.com'),
            ],
        ],
        'ebay' => [
            'driver' => env('PI_EBAY_DRIVER', 'auto'), // auto|api|scrape
            'client_id' => env('PI_EBAY_CLIENT_ID'),
            'client_secret' => env('PI_EBAY_CLIENT_SECRET'),
            'endpoint' => env('PI_EBAY_ENDPOINT', 'https://api.ebay.com'),
            'marketplace_id' => env('PI_EBAY_MARKETPLACE_ID', 'EBAY_IT'),
        ],
        'google_shopping' => [
            'driver' => env('PI_GOOGLE_DRIVER', 'auto'), // auto|serp|scrape
            'serp' => [
                'key' => env('PI_SERPAPI_KEY'),
                'endpoint' => env('PI_SERPAPI_ENDPOINT', 'https://serpapi.com/search'),
                'gl' => env('PI_SERPAPI_GL', 'it'),
                'hl' => env('PI_SERPAPI_HL', 'it'),
            ],
        ],
        'idealo' => ['driver' => 'scrape'],
        'trovaprezzi' => ['driver' => 'scrape'],
        'farfetch' => [
            'driver' => env('PI_FARFETCH_DRIVER', 'scrape'), // scrape|retailed|apify
            'retailed' => [
                'key' => env('PI_RETAILED_KEY'),
                'endpoint' => env('PI_RETAILED_ENDPOINT', 'https://app.retailed.io/api/v1/scraper/farfetch/product'),
            ],
            'apify' => [
                'token' => env('PI_APIFY_TOKEN'),
                'actor' => env('PI_APIFY_FARFETCH_ACTOR', 'autofacts~farfetch'),
                'endpoint' => env('PI_APIFY_ENDPOINT', 'https://api.apify.com/v2'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product matching
    |--------------------------------------------------------------------------
    */
    'matching' => [
        'confidence_band' => [60, 85],
        // Borderline LLM judge step (appended to the cascade when enabled). The model/provider
        // come from the shared ai.llm.* block below.
        'llm' => ['enabled' => true],
        'embeddings' => [
            // 'fake' (default, offline-deterministic) | 'laravel-ai' (uses the laravel/ai SDK)
            'driver' => env('PI_EMBEDDINGS_DRIVER', 'fake'),
            'provider' => env('PI_EMBEDDINGS_PROVIDER', 'openai'),
            'model' => env('PI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('PI_EMBEDDINGS_DIMENSIONS', 1536),
            'cache_ttl' => 2592000,
        ],
        'recompute_on_dom_diff_threshold' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage / time-series
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'partitioning' => ['enabled' => false, 'months_ahead' => 3],
        'retention' => ['raw_days' => 90],
        'aggregates' => ['enabled' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience
    |--------------------------------------------------------------------------
    */
    'resilience' => [
        'adaptive_backoff' => ['enabled' => true, 'max_factor' => 4],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI features (each individually toggleable)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        // Shared LLM backing for every AI feature (narrative, content-gap, promo, visual, match-judge).
        'llm' => [
            // 'fake' (default, offline deterministic) | 'laravel-ai' (uses the laravel/ai SDK)
            'driver' => env('PI_LLM_DRIVER', 'fake'),
            // config/ai.php provider key: openai | anthropic | gemini | regolo | ...
            'provider' => env('PI_LLM_PROVIDER', 'openai'),
            'model' => env('PI_LLM_MODEL', 'gpt-4o-mini'),
            'vision_model' => env('PI_LLM_VISION_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('PI_LLM_TIMEOUT', 120),
        ],
        'visual_match' => ['enabled' => true],
        'content_gap' => ['enabled' => true],
        // Forecast driver is selected by binding ForecastProviderInterface (Statistical by
        // default); there is no string "driver" switch here to avoid a dead setting.
        'forecast' => ['enabled' => true, 'min_observations' => 14, 'show_confidence_interval' => true],
        'anomaly' => ['enabled' => true],
        'narrative' => ['enabled' => true],
        'promo_detection' => ['enabled' => true],
        'assortment' => ['enabled' => true],
    ],

    'review_insight' => [
        'enabled' => false,
        'allowed_domains' => [],
    ],

    // Advisory-only by design: the repricer NEVER applies prices (RuleDecision.applied stays
    // false; a RepricingSuggested event is emitted for the host to act on). No "dry run" switch.
    'repricer' => [
        'enabled' => false,
        // Deprecated no-op, retained for backward-compatibility: the repricer is ALWAYS
        // advisory (it never applies prices), so there is effectively only "dry run".
        'dry_run_only' => true,
        // Custom strategies: prefer registering callables in the container under
        // "price-intelligence.repricer.custom.{name}" (config:cache safe). A map of
        // name => invokable class-string may also be declared here.
        'custom' => [],
    ],

    'pii' => ['enabled' => 'auto'],

    'ai_act' => [
        'enabled' => 'auto',
        'disclosure' => ['enabled' => true],
        'decision_log' => ['enabled' => true],
    ],

    'compliance' => [
        'robots' => ['default' => 'respect'],
        'rate_limit' => ['default_rpm' => 30],
        'audit' => ['enabled' => true, 'retention_days' => 90],
    ],

    'fx' => [
        'driver' => env('PI_FX_DRIVER', 'fixed'),
        'base' => 'EUR',
    ],

    'webhooks' => [
        'retry' => 5,
        'daily_digest' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix' => 'api/v1',
        'middleware' => ['api'],
        'register_routes' => true,
        // Resolve the tenant id from the authenticated Sanctum user (defaults to
        // $user->tenant_id when null). For `php artisan config:cache` compatibility set
        // this to an invokable class-string (resolved via the container), not a closure.
        'tenant_resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue lanes (used as queue names / Horizon tags)
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'discovery' => 'pi-discovery',
        'scrape' => 'pi-scrape',
        'enrich' => 'pi-enrich',
        'ai' => 'pi-ai',
        'notifications' => 'pi-notifications',
    ],
];
