# Phase 6 database maintenance

## PostgreSQL baseline

Phase 6 remains PostgreSQL-first. Capacity reviews should track relation/index growth, dead tuples, long-running transactions, lock waits, autovacuum effectiveness, connection saturation, and sequential-scan growth on high-volume operational tables.

High-growth Phase 6 tables are `webhook_deliveries`, `alliance_usage_snapshots`, `outbox_messages`, `audit_events`, and existing event/contribution/recruitment history. The Phase 6 migration adds indexes for tenant/status/time access paths used by the scheduler and operator console.

## Routine maintenance

- Keep PostgreSQL autovacuum enabled; tune per-table thresholds only from measured churn.
- Review `pg_stat_user_tables` for dead tuples and vacuum lag.
- Review `pg_stat_user_indexes` for unused or disproportionately large indexes before adding new ones.
- Use `EXPLAIN (ANALYZE, BUFFERS)` on slow production-equivalent queries in staging, never by experimenting on an overloaded production transaction.
- Monitor database connections and pool size against PostgreSQL limits.
- Keep statistics current after unusually large data loads or purges.
- Run retention in bounded batches/maintenance windows if data volume makes a single delete materially expensive.

## Index review targets

The platform console caps fleet retrieval at 200 alliances and uses correlated tenant aggregates. As fleet size grows, evaluate materialized usage snapshots as the primary dashboard source rather than removing the bound. Webhook recovery depends on `status + available_at`; outbox publication depends on publish/availability state; lifecycle recovery depends on `retention_until`.

Do not add speculative indexes solely because a column appears in a filter. Capture execution plans and query frequency first because every index increases write and vacuum cost.

## Backups

Backups must include the complete PostgreSQL database and private media/object storage. A database-only restore is insufficient for media-bearing tenants. Encryption keys used for application encrypted casts (including webhook signing secrets) must be included in secure configuration recovery; without the application key, restored encrypted fields cannot be used.

Restore exercises should validate row counts, schema migration level, representative media availability, API credential verifier behavior, decryptability of encrypted webhook secrets, queue restart behavior, and a known audit/outbox trail.
