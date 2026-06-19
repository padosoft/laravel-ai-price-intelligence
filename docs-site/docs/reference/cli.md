---
title: CLI
---

# CLI Reference

| Command | Purpose |
|---|---|
| `piprice:catalog:import` | Import catalog rows from CSV. |
| `piprice:run-due` | Run due monitoring targets. |
| `piprice:audit:prune` | Prune fetch audit logs by retention policy. |
| `piprice:aggregates:daily` | Materialize daily price aggregates. |

```bash
php artisan piprice:run-due
php artisan piprice:audit:prune
php artisan piprice:aggregates:daily
```

The package scheduler invokes due-target processing every minute when Laravel's scheduler is running.
