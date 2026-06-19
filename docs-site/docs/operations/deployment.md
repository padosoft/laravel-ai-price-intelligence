---
title: Deployment
---

# Deployment

Deploy the package inside a Laravel host application. Required runtime services depend on volume, but production usually needs PHP workers, a database, Redis, scheduler, and queue supervision.

```bash
php artisan migrate --force
php artisan schedule:work
php artisan queue:work --queue=pi-discovery,pi-scrape,pi-ai,pi-notifications
```

::: callout tip "Docs deployment"
The static documentation site is built from `docs-site` with Node 20 and outputs to `docs-site/_site`.
:::
