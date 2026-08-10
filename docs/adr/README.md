# Architecture decision records

[← Documentation home](../README.md)

Kingshot Alliance is an enterprise modular monolith organized by explicit business domains. The canonical physical repository structure is defined by the [implementation plan](../product/implementation-plan.md), [documentation standard](../product/documentation-standard.md), and ADR 0008; approved post-program capability is recorded through named product increments under `../product/`.

## Current architecture view

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
  - active-Alliance tenant context
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

The deployable application uses one immutable image for web/application, Horizon worker, scheduler, and one-shot migration roles. Standard hosted topology uses Nginx, PHP-FPM, PostgreSQL, Redis, Horizon, `schedule:work`, and private local/S3-compatible storage. Production Content media requires durable S3-backed storage.

See [Runtime configuration](../operations/configuration-reference.md), [Background processing](../operations/background-processing.md), and the [deployment runbook](../operations/runbooks/deployment.md).

### Domain ownership

Runtime PHP is domain-first under `app/Domain/<Domain>`. Canonical roots are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

Living documentation mirrors those roots one-to-one under `docs/domains/<domain>/README.md`. See the [domain index](../domains/README.md), [repository structure audit](../product/repository-structure-audit.md), and [domain boundary audit](../product/domain-boundary-audit.md).

Key ownership boundaries:

- [Identity](../domains/identity/README.md) owns global User/authentication/MFA assurance.
- [Alliances](../domains/alliances/README.md) owns the Alliance aggregate and active tenant context.
- [Memberships](../domains/memberships/README.md) owns membership/invitation lifecycle.
- [Authorization](../domains/authorization/README.md) owns roles/permissions/permission evaluation.
- [Audit](../domains/audit/README.md) owns attributable audit evidence.
- [Content](../domains/content/README.md) owns authored/public/member Content and media.
- [Events](../domains/events/README.md) owns Event schedules/occurrences/registration/Event attendance.
- [Rallies](../domains/rallies/README.md) owns Rally guidance/formations/groups/assignments/Rally participation.
- [Notifications](../domains/notifications/README.md) owns durable Event-reminder and scheduled-report due-time coordination.
- [Recruitment](../domains/recruitment/README.md) owns candidate/application/onboarding/retention workflow.
- [Contributions](../domains/contributions/README.md) owns contribution/calculation/reporting/export state.
- [Integrations](../domains/integrations/README.md) owns API credentials/read contracts and webhook subscription/delivery/signing/retry state.
- [Platform](../domains/platform/README.md) owns cross-tenant administration/lifecycle/entitlements/retention and transactional-outbox infrastructure.
- [Kingdoms](../domains/kingdoms/README.md) owns accepted Kingdom/player/game-Alliance references plus Alliance-owned roster/history/intelligence/transfer/diplomacy workflows.

Cross-domain collaboration should use intentional actions, queries, services, value objects, enums, or events rather than persistence reach-through.

### Tenancy and Kingdoms reference boundaries

Identity is global. Alliance-scoped behavior activates explicit Alliance context and requires active membership before tenant data is accessed. Platform administration is cross-tenant and does not reuse Alliance roles.

Kingdoms uses a deliberate global-reference/tenant-observation split. Sharing a `Kingdom`, `KingdomPlayer`, or `KingdomAlliance` reference never grants cross-Alliance access to roster state, notes, membership links, snapshots, imports, transfer plans, observations, diplomacy, contacts, or derived intelligence.

Stable game identifiers scoped to one Kingdom are the only automatic neutral identity keys; names/tags/handles never auto-merge identity.

### Synchronous and asynchronous boundaries

Normal HTTP requests execute synchronously inside the modular monolith and persist through domain-owned actions/services/models.

Platform owns the transactional outbox as the durable asynchronous boundary. The scheduler publishes eligible outbox messages; listeners coordinate downstream effects; webhook HTTP delivery runs through the isolated `integrations` queue.

Internal durable event publication does not automatically create a public webhook contract. `alliance.kingdom_updated` and all `kingdoms.*` events remain excluded from generic external webhook fan-out.

See [ADR 0004](0004-queues-and-transactional-outbox.md), [Notifications](../domains/notifications/README.md), [Integrations](../domains/integrations/README.md), [Kingdoms](../domains/kingdoms/README.md), and [Background processing](../operations/background-processing.md).

### Data and storage boundaries

- **PostgreSQL** — relational application state, tenant business records, and global neutral Kingdoms references.
- **Redis** — hosted cache, encrypted sessions, queues, and Horizon coordination.
- **Private object/file storage** — Content media/generated artifacts where applicable.
- **Audit** — attributable security/business evidence.
- **Transactional outbox** — durable asynchronous work before publication.

Tenant isolation is architectural: tenant identity must flow through queries, jobs, notifications, logs, cache keys, exports, and storage paths.

### Trust and integration boundaries

External API access uses Alliance-bound read-only credentials and fixed scopes. Outbound webhooks are signed/retried. Application URL validation is not a production network boundary; hosted infrastructure must separately enforce egress/SSRF controls.

The read-only Alliance API's `kingdom` field derives from the first-class Kingdom relation. No public Kingdoms roster/snapshot/intelligence/transfer/diplomacy API route/scope or public `kingdoms.*` webhook contract is accepted.

Privileged first-party access combines verified identity, MFA where required, recent password confirmation for sensitive actions, tenant context, and policy/permission checks. Identity assurance never replaces authorization.

### Observability and production boundary

Current runtime provides JSON stderr logging, request IDs, W3C trace correlation, liveness/readiness endpoints, Horizon/outbox/webhook visibility, and repository-controlled launch-health signals.

The repository does not currently configure an OpenTelemetry exporter. Laravel Pulse remains a foundation with hosted recording disabled until its schema/access policy is introduced.

Repository automation proves code/test quality, immutable image construction, staging boot, migrations, health checks, backup/restore tooling, and image scanning. It does **not** prove real production ingress/TLS, egress enforcement, alert ownership, capacity, operator identity, support coverage, managed dependency configuration, or production-managed key/media recovery.

Repository production hardening is accepted; real production cutover remains **not yet approved**. See [production launch approval](../product/production-launch-approval.md).

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

Use an ADR for a material architecture decision whose rationale/consequences should survive individual PRs. If a decision replaces an older ADR, mark the older record superseded rather than silently rewriting historical rationale.

An ADR may refine architecture but must not silently expand product scope. The implementation plan remains authoritative for the completed baseline; approved named product increments extend product scope explicitly.

## Canonical source/documentation structure

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
    README.md
    alliances/README.md
    audit/README.md
    authorization/README.md
    content/README.md
    contributions/README.md
    events/README.md
    identity/README.md
    integrations/README.md
    kingdoms/README.md
    memberships/README.md
    notifications/README.md
    platform/README.md
    rallies/README.md
    recruitment/README.md
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

Runtime PHP is owned by a canonical `app/Domain/<Domain>` module. Capability docs live inside the matching documentation domain directory. Architecture documentation must not introduce parallel top-level source/docs/test groupings that conflict with the implementation plan, documentation standard, or architecture tests.
