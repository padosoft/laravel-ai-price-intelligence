# Competitive Matrix

How `laravel-ai-price-intelligence` compares to the leading commercial competitor-monitoring SaaS,
**Netrivals** (by Lengow) and **Competitoor**. Legend: ✅ full · ⚠️ partial/limited · ❌ none.

> Competitor capabilities are summarized from their public product pages
> (lengow.com/solutions/netrivals, competitoor.com) as of 2026-05. Verify current offerings with
> the vendors; this matrix reflects public positioning, not contractual feature lists.

| Capability | Netrivals | Competitoor | **this package** |
|---|:-:|:-:|:-:|
| Price & stock monitoring, multi-country | ✅ | ✅ | ✅ (per-target country + locale) |
| AI product matching | ✅ | ⚠️ | ✅ cascade GTIN→MPN→name→embedding→vision, confidence + review queue |
| Visual matching (vision LLM) | ❌ | ❌ | ✅ interface-ready |
| Dedicated marketplace adapters (Amazon buy-box, eBay…) | ✅ | ⚠️ | ✅ Amazon/eBay/Google Shopping/Idealo/Trovaprezzi |
| Repricing / dynamic pricing | ✅ | ✅ | ✅ no-code, **advisory-only** (off by default) |
| Assortment intelligence | ✅ | ✅ | ✅ interface-ready (+ gap scoring) |
| Promo normalization (list vs sale vs bundle) | ⚠️ | ⚠️ | ✅ interface-ready |
| Price forecasting per-SKU | ❌ | ❌ | ✅ statistical (pluggable ML) |
| Anomaly detection (price errors, baits, batch updates) | ❌ | ❌ | ✅ |
| Weekly AI narrative digest | ❌ | ❌ | ✅ interface-ready |
| Content-gap analysis (SEO/copy vs competitors) | ⚠️ | ⚠️ | ✅ interface-ready |
| Review sentiment, GDPR-safe | ❌ | ❌ | ✅ anonymous aggregates, mandatory PII redaction |
| **EU AI Act-ready by design** | ❌ | ❌ | ✅ disclosure + decision log + compliance bridge |
| GDPR PII redaction integrated | ❌ | ⚠️ | ✅ via laravel-pii-redactor |
| Open-source, self-hostable | ❌ SaaS | ❌ SaaS | ✅ Apache-2.0, on-premise |
| API-first + signed (HMAC) webhooks | ⚠️ | ✅ | ✅ Sanctum + API key + scopes |
| Native Laravel/ecommerce integration | ❌ | ❌ | ✅ |
| Pricing model | €€€ licence | €€€ licence | infra + AI usage at cost, config-budget controllable |

## Positioning

The only **open-source, AI-native, EU-compliant-by-design** competitor price & product intelligence
engine that is **self-hostable** and integrates **natively into a Laravel/ecommerce stack** — with
forecasting, anomaly detection, GDPR-safe review sentiment and an EU AI Act posture that the
commercial incumbents do not offer integrated.
