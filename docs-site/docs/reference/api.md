---
title: API
---

# API Reference

Base path defaults to `/api/v1`.

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/catalog/products:bulk` | Bulk upsert products. |
| `POST` | `/catalog/products:csv` | Import catalog CSV. |
| `GET` | `/catalog/products` | Cursor-paginated product list. |
| `POST` | `/targets` | Create a monitoring target. |
| `PATCH` | `/targets/{id}` | Pause, resume, or reschedule a target. |
| `POST` | `/targets/{id}/discover:now` | Queue immediate discovery. |
| `GET` | `/matches?status=pending` | Review match proposals. |
| `POST` | `/matches/{id}/approve` | Confirm a proposal. |
| `POST` | `/matches/{id}/reject` | Reject a proposal. |
| `GET` | `/alerts` | List alerts. |
| `POST` | `/alerts/{id}/ack` | Acknowledge an alert. |
| `POST` | `/webhook-subscriptions` | Create webhook subscription. |

::: callout info "Authentication"
Machine-to-machine calls use `X-Api-Key`. UI contexts can use Sanctum when the host enables it.
:::
