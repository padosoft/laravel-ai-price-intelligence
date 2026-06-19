---
title: Scaling Large Catalogs
---

# Scaling Large Catalogs

For catalogs around 500k SKUs, keep every workflow cursor-based, queue-based, and aggregate-aware.

::: tabs
== tab "Database"
Use composite indexes, partition-friendly time-series tables, and daily aggregates.

== tab "Queues"
Separate discovery, scrape, AI, and notification lanes.

== tab "Search"
Cache discovery results and avoid re-discovering confirmed matches unless evidence changes.
:::

The practical goal is to make dashboards query aggregates, workers process bounded batches, and humans review only the uncertain subset.
