# Architecture decision records

[← Documentation home](../README.md)

Kingshot Alliance is an enterprise modular monolith organized by explicit business domains. The canonical physical repository structure is defined by the [implementation plan](../product/implementation-plan.md) and ADR 0008; approved post-program scope is recorded through named product increments under `../product/`.

## Current architecture view

This section is a living system map for the current Phase 0–6-complete runtime plus implemented post-program increments. It summarizes accepted decisions and current implementation boundaries; the numbered ADRs remain the durable record of why those decisions were made. Approved roadmap scope is called out separately and must not be confused with runtime capability.

```text
Browser / API client
        |
        v
  Nginx web entry point
        |
        v
  Laravel application
  - request/trace correlation
  - authentication / MFA / password confirmation
  - active-alliance tenant context
  - policy / permission checks
        |
        +--------------------------+
        |                          |
        v                          v
Business domains             Platform/foundation domains
- Alliances                  - Audit
- Memberships                - Platform
- Authorization              - Integrations
- Identity                   - Notifications
- Content
- Events / Rallies
- Recruitment
- Contributions
- Kingdoms
        |                          |
        +------------+-------------+
                     |
        +------------+-------------+----------------+
        |                          |                 |
        v                          v                 v
   PostgreSQL                   Redis          Private media
   system of record       cache/session/queue   local or S3
        |
        v
 transactional outbox
        |
        v
 scheduler / event listeners -----> Horizon integrations queue -----> signed webhooks
```

### Runtime topology

The deployable application uses one immutable image for the web/application, Horizon worker, scheduler, and one-shot migration roles. The standard staging topology runs:

- Nginx as the HTTP entry point;
- PHP-FPM for the Laravel application;
- PostgreSQL as the hosted relational system of record;
- Redis for hosted cache, encrypted sessions, and queues;
- Horizon for queue workers;
- `schedule:work` for recurring/background coordination; and
- private local or S3-compatible storage, with production content media requiring durable S3-backed storage.

See [Runtime configuration reference](../operations/configuration-reference.md), [Background processing](../operations/background-processing.md), and the [deployment runbook](../operations/runbooks/deployment.md) for the executable operating contract.

### Domain ownership

Runtime PHP is domain-first under `app/Domain/<Domain>`. The canonical roots are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

`Kingdoms` now owns the implemented `KINGDOMS-001` Slice A foundation: global first-class Kingdom reference records, canonical Kingdom resolution, and the business workflow for changing an alliance's Kingdom association. `Alliances` owns the Alliance aggregate and its foreign-key relationship to that reference. `Content` no longer owns Kingdom mutation.

The remaining approved `KINGDOMS-001` capabilities—game-player identity, alliance roster entries, snapshots, roster intelligence and CSV workflows—are not current runtime capability until their implementation phases are completed. See the living [Kingdoms guide](../domains/kingdoms.md) and [KINGDOMS-001 implementation plan](../product/kingdoms-roster-intelligence-implementation-plan.md).

Identity is global. Alliance-scoped business behavior activates an explicit alliance context and requires an active membership before tenant data is accessed. Platform administration is intentionally cross-tenant and does not reuse alliance roles as its authorization model. These boundaries follow ADR 0002 and the living [identity/tenancy/membership](../domains/identity-tenancy-and-membership.md) and [platform administration](../domains/platform-scale-and-administration.md) contracts.

### Synchronous and asynchronous boundaries

Normal HTTP requests execute synchronously inside the modular monolith and persist through domain-owned models/services. Cross-domain collaboration should use intentional actions, queries, services, value objects, or events rather than persistence reach-through.

The transactional outbox is the durable asynchronous boundary for domain events that must survive the originating transaction. The scheduler publishes eligible outbox messages, and listeners coordinate downstream effects such as reminder state, recruitment conversion state, and webhook creation. Webhook HTTP delivery itself runs through Horizon's `integrations` queue so external retries cannot consume all core application worker capacity.

The Kingdom association mutation follows the same rule: the relationship change, audit record and `alliance.kingdom_updated` outbox message are recorded inside the transaction; no new Kingdoms-specific queue or scheduler is introduced by Slice A.

See [ADR 0004](0004-queues-and-transactional-outbox.md), [Notifications](../domains/notifications.md), [Integrations](../domains/integrations.md), [Kingdoms](../domains/kingdoms.md), and [Background processing](../operations/background-processing.md).

### Data and storage boundaries

- **PostgreSQL** owns relational application state and tenant-scoped business records, plus global neutral reference records such as the current Kingdom catalog.
- **Redis** owns hosted cache, encrypted session state, queue transport, and Horizon coordination.
- **Private object/file storage** owns content media and generated operational artifacts where applicable; production content media requires S3-backed storage.
- **Audit records** persist security/business audit events with request/trace correlation when created in an HTTP context.
- **Transactional outbox records** persist durable asynchronous work before publication.

Tenant isolation is enforced as an architectural property, not merely a naming convention. Tenant-bound cache/storage keys and cross-domain queries must preserve alliance identity explicitly.

A global Kingdom reference does not create a cross-alliance tenant boundary. Multiple alliances may reference the same Kingdom while their authorization and alliance-owned data remain isolated. The approved later `KINGDOMS-001` model preserves this rule by keeping roster entries, notes, snapshots, imports, exports, and derived metrics alliance-scoped even when neutral Kingdom/player references are global.

### Trust and integration boundaries

External API access uses alliance-bound read-only credentials and fixed scopes. Outbound webhooks are signed and retried, but deployment infrastructure must still enforce egress/SSRF controls; application URL validation alone is not a production network boundary.

The existing read-only alliance API retains its `kingdom` response field, now derived from the first-class Kingdom relationship. This is API representation compatibility and does not preserve the removed free-form persistence model.

Privileged web access uses verified identity, MFA where required, recent password confirmation for sensitive actions, and policy/permission checks. Platform administration has its own grant model and stronger cross-tenant access requirements. Slice A Kingdom-association changes reuse `alliance.manage`; the planned `kingdoms.manage` permission is deferred until Slice B introduces roster mutations.

See the [security baseline](../security/security-baseline.md), [Integrations](../domains/integrations.md), [Kingdoms](../domains/kingdoms.md), and [production launch security review](../security/production-launch-security-review.md).

### Observability and health

The current runtime provides JSON stderr logging, UUID request IDs, W3C trace correlation, request completion/failure logs, audit correlation, `/up` liveness, `/health/ready` database/cache readiness, Horizon queue visibility, and repository-controlled launch-health counters.

The repository does **not** currently configure an OpenTelemetry exporter or inject release/tenant metadata into every log record. Laravel Pulse is present as a foundation but hosted configuration requires Pulse recording to remain disabled until its schema and access policy are introduced.

See [ADR 0006](0006-observability-and-correlation.md) and [Observability](../operations/observability.md).

### Deployment and production boundary

Repository automation proves code/test quality, immutable image construction, staging boot, migrations, health checks, backup/restore tooling, and image scanning. It does not prove real production ingress/TLS, egress enforcement, alert ownership, capacity, operator identity, support coverage, managed dependency configuration, or recovery of production-managed keys/media.

Repository production hardening is accepted; a real production cutover remains **not yet approved**. Implementation of a `KINGDOMS-001` slice does not change that production decision. The authoritative decision remains [production launch approval](../product/production-launch-approval.md).

## Decision records

- [ADR 0001 — Modular monolith](0001-modular-monolith.md)
- [ADR 0002 — Alliance-level tenancy](0002-alliance-level-tenancy.md)
- [ADR 0003 — First-party authentication](0003-first-party-authentication.md)
- [ADR 0004 — Queues and transactional outbox](0004-queues-and-transactional-outbox.md)
- [ADR 0005 — S3-compatible object storage](0005-s3-compatible-object-storage.md)
- [ADR 0006 — Observability and correlation](0006-observability-and-correlation.md)
- [ADR 0007 — Testing toolchain compatibility](0007-testing-toolchain-compatibility.md)
- [ADR 0008 — Domain-first source layout](0008-domain-first-source-layout.md)

Use [the ADR template](adr-template.md) for new material decisions.

## ADR lifecycle

Use an ADR for a material architecture decision whose rationale and consequences should survive individual PRs. Prefer updating operational/domain documentation for implementation detail that does not change architecture.

A new ADR should clearly identify its status and relationship to earlier decisions. If a decision replaces an older ADR, mark the older record superseded rather than silently rewriting the historical rationale. The implementation plan remains authoritative for the completed Phase 0–6 baseline and canonical repository groups; approved product-increment scopes may extend product scope after that baseline. An ADR may refine the architecture but must not silently expand product scope.

## Canonical source structure

```text
app/
  Domain/
    Alliances/
    Audit/
    Authorization/
    Content/
    Contributions/
    Events/
    Identity/
    Integrations/
    Kingdoms/
    Memberships/
    Notifications/
    Platform/
    Rallies/
    Recruitment/
docs/
  adr/
  domains/
  operations/
  product/
  security/
resources/js/
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Runtime PHP is owned by a canonical `app/Domain/<Domain>` module. Internal organization such as `Actions`, `Queries`, `Services`, `Models`, `Http`, `Enums`, and `ValueObjects` lives inside the owning domain rather than in parallel top-level application layers.

Domains should communicate through intentional public actions, queries, services, value objects, or events. A cross-domain dependency must be part of the other domain's supported contract rather than an accidental dependency on its persistence internals.

Architecture documentation must not introduce additional top-level `app/`, `docs/`, or `tests/` groupings that conflict with the baseline implementation plan, approved increment scope, and repository-structure tests.
