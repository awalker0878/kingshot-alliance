# Phase 6 Exit Report — Platform Scale and Administration

**Status:** Candidate — final protected validation pending  
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
- Phase 6 accessibility guard and bounded capacity/retry tests.

## Documentation

- `docs/domains/PLATFORM_SCALE_AND_ADMINISTRATION.md`
- `docs/domains/INTEGRATIONS.md`
- `docs/operations/PHASE_6_OPERATIONS.md`
- `docs/operations/PHASE_6_DATABASE_MAINTENANCE.md`
- `docs/operations/PHASE_6_MIGRATION_ROLLBACK.md`
- `docs/operations/PHASE_6_DISASTER_RECOVERY_EXERCISE.md`
- `docs/security/PHASE_6_THREAT_MODEL.md`
- `docs/product/PHASE_6_ACCESSIBILITY.md`
- `docs/product/PHASE_6_LAUNCH_READINESS.md`

## Final gate

This report remains **Candidate** until the exact final PR head passes PostgreSQL migration, PHP formatting/static analysis, the full backend/frontend tests, tenant-isolation and migration tests, Dependency Review, CodeQL, immutable-image build, ephemeral staging deployment, backup/restore demonstration, and image scanning. After those gates are green and review/hygiene checks are clean, this report may be updated to **Accepted** immediately before merge.
