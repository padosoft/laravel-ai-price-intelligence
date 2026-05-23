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
        'amazon' => ['driver' => 'auto', 'rate_limit_rpm' => 20],
        'ebay' => ['driver' => 'api'],
        'google_shopping' => ['driver' => 'serp'],
        'idealo' => ['driver' => 'scrape'],
        'trovaprezzi' => ['driver' => 'scrape'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product matching
    |--------------------------------------------------------------------------
    */
    'matching' => [
        'confidence_band' => [60, 85],
        'llm' => ['enabled' => true, 'model' => 'gpt-4o-mini'],
        'embeddings' => ['driver' => 'fake', 'cache_ttl' => 2592000],
        'visual' => ['enabled' => true, 'driver' => 'fake'],
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
        'visual_match' => ['enabled' => true],
        'content_gap' => ['enabled' => true],
        'forecast' => ['enabled' => true, 'min_observations' => 14, 'show_confidence_interval' => true, 'driver' => 'statistical'],
        'anomaly' => ['enabled' => true],
        'narrative' => ['enabled' => true, 'driver' => 'fake'],
        'promo_detection' => ['enabled' => true, 'driver' => 'fake'],
        'assortment' => ['enabled' => true],
    ],

    'review_insight' => [
        'enabled' => false,
        'allowed_domains' => [],
    ],

    'repricer' => [
        'enabled' => false,
        'dry_run_only' => true,
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
