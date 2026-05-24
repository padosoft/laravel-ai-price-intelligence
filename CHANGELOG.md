# Changelog

All notable changes to `padosoft/laravel-ai-price-intelligence` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0-alpha] - 2026-05-24

First public alpha. Enterprise Product & Price Intelligence / Competitor Monitoring for Laravel.

### Added
- **Foundations**: multi-tenant (single-DB + database-per-tenant ready), Sanctum + API-key auth with
  scopes, `ResolveTenant` middleware, configurable table names, idempotent migrations.
- **Catalog onboarding**: bulk JSON + CSV import, idempotent upsert, monitoring targets (product × country),
  `piprice:catalog:import` command.
- **Discovery & matching**: geo-aware competitor discovery via `padosoft/laravel-ai-search-providers`;
  cascade matcher (GTIN → MPN+brand → normalized name → embedding) with confidence band
  (≥85 auto-confirm / 60–84 review / <60 reject) and an admin review queue.
- **Scraping**: JSON-LD + OpenGraph extractor, generic HTTP driver, marketplace adapters
  (Amazon, eBay, Google Shopping, Idealo, Trovaprezzi) + factory.
- **Pricing & storage**: multi-currency FX normalization to a base currency; time-series observations
  (price/content/stock/promo) + fetch audit log.
- **Scheduling**: adaptive backoff + per-lane Horizon queues; `piprice:run-due` (auto-scheduled).
- **Alerts & webhooks**: price drop/raise, undercut, stock-out; HMAC-signed webhooks (when a secret is set).
- **AI layer**: statistical price forecasting (OLS + confidence interval) and anomaly detection
  (detrended-residual outliers + price-error), pluggable + toggleable; `ai_decision_logs` + `is_ai_generated`.
- **Review sentiment** (off by default): GDPR-safe, per-domain opt-in, mandatory PII redaction,
  anonymous aggregates only.
- **Repricer** (off by default, advisory-only): no-code strategies (match_cheapest, undercut_pct,
  beat_top_n, match_with_floor, dynamic_demand, custom) with margin floor / max-change / charm guards;
  emits `RepricingSuggested` and never applies prices.
- **Compliance**: robots.txt parser + per-domain policy, atomic per-domain rate limiter, PII redaction
  of scraped content, EU AI Act bridge (`AiActBridgeInterface` + null-object), `piprice:audit:prune`.
- **Docs**: README (with web-panel showcase), INTEGRATION-GUIDE, EXTENDING, COMPETITIVE-MATRIX, PROJECT spec.

### Notes
- Companion web admin panel: [`padosoft/laravel-ai-price-intelligence-admin`](https://github.com/padosoft/laravel-ai-price-intelligence-admin).
- Apache-2.0. PHP 8.3+, Laravel 11/12/13.

[Unreleased]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.0.0-alpha...HEAD
[1.0.0-alpha]: https://github.com/padosoft/laravel-ai-price-intelligence/releases/tag/v1.0.0-alpha
