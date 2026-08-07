# Architecture

Kingshot Alliance is an **enterprise modular monolith**: one deployable Laravel application and database with explicit domain ownership, application boundaries, authorization, and tenant isolation.

The canonical architecture index is [docs/architecture/README.md](../architecture/README.md).

## Core principles

- Global users can participate in multiple alliances.
- Alliance membership establishes the tenant boundary for alliance-scoped behavior.
- Tenant identity is explicit rather than hidden in global state.
- Policies and permission services are the authoritative access-control layer.
- Controllers stay thin; application actions, services, commands, and queries own use-case logic.
- Cross-domain composition happens through application boundaries rather than direct ownership of another domain's internal tables.
- Meaningful persisted changes use domain events and the transactional outbox.
- Queued and integration work must be idempotent and tenant-aware.
- Time values are stored in UTC with explicit alliance and user time zones.
- Future-phase capabilities are not introduced as placeholders.

## Application layers

```text
app/
  Application/       orchestration and use cases
  Domain/            business rules grouped by domain
  Http/              controllers, middleware, request/response delivery
  Infrastructure/    persistence, queues, storage, integrations
  Providers/         composition root
```

## Implemented domain ownership

### Identity and multi-tenancy

Owns global identity, authentication, verification, MFA, alliances, memberships, active-alliance context, roles, permissions, invitations, audit attribution, and the shared outbox foundation.

### Content and public presence

Owns public profile description/branding, public/member content, categories, revisions, publication scheduling, search/filtering, and media lifecycle.

Recruitment availability is **not** duplicated here. The public alliance page obtains authoritative recruitment state from the Recruitment application boundary.

### Events and rallies

Owns event definitions and occurrences, registration/waitlist behavior, reminders, attendance, member formations, rally guidance, rally groups, assignments, participation facts, authenticated event exports, and member event views.

### Recruitment

Owns application mode/open state, questions, public or invitation-only intake, candidate workflow, reviewer notes/tags, stage history, duplicate/merge behavior, decision records, membership-invitation conversion, recruitment metrics, and unsuccessful-candidate retention.

## Cross-domain rules

- Core tenant models remain shared foundation objects; later domains do not create parallel membership or alliance systems.
- Recruitment uses the Phase 1 invitation application action when converting accepted candidates.
- Content reads recruitment state through a Recruitment query rather than storing a second writable status.
- Phase 3 participation data remains operational event/rally data. Phase 5 scoring and contribution logic must not be backfilled into Phase 3.

## Architecture decisions

The repository currently records ADRs for:

1. Modular monolith
2. Alliance-level tenancy
3. First-party authentication
4. Queues and transactional outbox
5. S3-compatible object storage
6. Observability and correlation
7. Testing-toolchain compatibility

Use the repository [ADR template](../architecture/adr-template.md) for material decisions.

## Integration baseline

The [Phases 1–4 alignment audit](../PHASES_1_4_ALIGNMENT_AUDIT.md) is the current integrated ownership reference. It intentionally removed duplicate state, future-phase permission placeholders, and inconsistent privileged-action confirmation boundaries rather than preserving compatibility shims in a pre-production system.
