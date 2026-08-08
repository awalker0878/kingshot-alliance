# Phase 6 Exit Report — Platform Scale and Administration

**Status:** Accepted  
**Phase:** 6 — Platform scale and administration

## Outcome

Phase 6 adds the cross-tenant controls and operating foundations required to support controlled multi-alliance growth: strict platform administration, tenant lifecycle/ownership operations, plans and quotas, configuration/feature flags, operational usage visibility, API credentials, signed webhooks, retention/legal holds/account deletion, tenant-complete export, queue partitioning, and DR/launch-readiness procedures.

Support impersonation is intentionally not implemented because the implementation plan permits it only if explicitly approved and no approval exists.

## Implementation evidence

### Platform boundary

- Platform administrators are stored separately from alliance roles/permissions.
- Platform web access requires authentication, verified email, MFA, active platform-admin grant, and recent password confirmation.
- Horizon uses the platform-admin/MFA boundary.
- Platform routes do not activate alliance tenant context.

### Tenant lifecycle and configuration

- Platform provisioning, suspension, close, logical delete, restore, export, ownership transfer, plan assignment, operational settings, usage capture, and feature flags are implemented.
- Inactive alliances fail closed for member tenant context and API authentication.
- Logical deletion requires closure, is blocked by legal hold, and remains restorable until the retention deadline.
- New and existing alliances receive a standard plan/settings baseline.

### Capacity and entitlement enforcement

- Member quota is enforced during invitation creation.
- Storage quota is enforced before media persistence.
- API credential and webhook subscription counts are plan-limited.
- Fleet usage snapshots and queue/integration metrics support capacity review.
- Horizon separates core, integrations, and maintenance worker capacity.

### Integrations

- Scoped one-time API credentials are hash-stored, expiry-aware, revocable, rate-limited, and tenant-bound.
- Read-only `/api/v1` alliance/events/contributions endpoints demonstrate scope enforcement.
- Webhooks use encrypted signing secrets, event allow-lists, HTTPS/private-address policy, HMAC-SHA256 signatures, idempotent outbox fan-out, isolated queue workers, bounded retry/backoff, delivery logs, and recovery scanning.
- Webhook payloads are limited to 256 KiB and old payload/error details are redacted by retention.

### Data lifecycle

- Legal holds can protect users or alliances and are auditable.
- Account deletion has a seven-day cooling-off period and is blocked by active platform administration, alliance ownership, or legal hold.
- Processed account deletion revokes tokens, ends active memberships, and anonymizes identity while preserving pseudonymized history.
- Alliance JSON export discovers tenant-bearing PostgreSQL tables, filters by alliance, redacts known secret columns, records schema version/row count/SHA-256/table counts, and has a synchronous size safety bound.

## Automated verification added

- platform-admin separation, MFA/password requirements, alliance lifecycle and legal-hold behavior;
- API token scope/revocation/tenant binding;
- webhook private-destination rejection, idempotent fan-out, signature verification, and delivery state;
- account deletion blockers/legal holds/anonymization;
- Phase 6 migration rollback/reapply;
- integration cross-tenant identifier denial;
- architecture guard advancing Integrations while keeping Kingdoms runtime-free;
- Phase 6 accessibility guard and bounded capacity/retry tests;
- hosted runtime validation for the Phase 6 Horizon `core`, `integrations`, and `maintenance` supervisor partitions;
- immutable staging startup that resolves the protected `/platform` route in addition to health/readiness endpoints.

## Documentation

- `docs/domains/platform-scale-and-administration.md`
- `docs/domains/integrations.md`
- `docs/operations/phase-6-operations.md`
- `docs/operations/phase-6-database-maintenance.md`
- `docs/operations/phase-6-migration-rollback.md`
- `docs/operations/phase-6-disaster-recovery-exercise.md`
- `docs/security/phase-6-threat-model.md`
- `docs/product/phase-6-accessibility.md`
- `docs/product/phase-6-launch-readiness.md`

## Final gate

Phase 6 is accepted for merge. The protected validation run on code head `d1969889ffa044cd7690f263ba9ef70c63a425cb` passed PostgreSQL migration, PHP formatting/static analysis, the complete backend/frontend suites, Dependency Review, CodeQL, immutable production-image build, ephemeral staging deployment including `/platform` controller resolution, backup/restore, and image scanning. Review hygiene was also clean: no review submissions, inline review threads, or PR comments remained, and temporary diagnostic artifacts were removed.

The merge head must retain the same protected green state after this documentation finalization commit; any regression reopens the Phase 6 gate.
