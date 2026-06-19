---
title: Installation
---

# Installation

The package installs into a Laravel host application.

```bash
composer require padosoft/laravel-ai-price-intelligence
php artisan vendor:publish --tag=price-intelligence-config
php artisan migrate
```

## Optional Dependencies

::: tabs
== tab "Queues"
Use `laravel/horizon` for queue supervision when scraping at volume.

== tab "Rendering"
Use `spatie/browsershot` for JavaScript-heavy pages that cannot be handled by generic HTTP extraction.

== tab "Compliance"
Use `padosoft/laravel-pii-redactor` for redaction and `padosoft/laravel-ai-act-compliance` for governance bridge records.

== tab "Excel"
Use `phpoffice/phpspreadsheet` when XLSX import or export is required.
:::

The default LLM driver is fake and deterministic, so local development and CI do not require external model keys.
