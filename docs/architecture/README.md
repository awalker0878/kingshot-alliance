# Architecture

Kingshot Alliance is an enterprise modular monolith. Product domains share one deployable application and database while maintaining explicit ownership, interfaces, authorization, and data boundaries.

## Decision records

- [ADR 0001 — Modular monolith](0001-modular-monolith.md)
- [ADR 0002 — Alliance-level tenancy](0002-alliance-level-tenancy.md)
- [ADR 0003 — First-party authentication](0003-first-party-authentication.md)
- [ADR 0004 — Queues and transactional outbox](0004-queues-and-transactional-outbox.md)
- [ADR 0005 — S3-compatible object storage](0005-s3-compatible-object-storage.md)
- [ADR 0006 — Observability and correlation](0006-observability-and-correlation.md)

Use [the ADR template](adr-template.md) for new material decisions.

## Source structure

```text
app/
  Application/       orchestration and use cases
  Domain/            business rules grouped by domain
  Http/              delivery adapters
  Infrastructure/    database, messaging, storage, and external adapters
  Providers/         composition root
```

Only `Shared` foundation code may exist before a product domain is implemented. Later domains must not depend on another domain's internal models or tables.

- [ADR 0007: Testing toolchain compatibility](0007-testing-toolchain-compatibility.md)
