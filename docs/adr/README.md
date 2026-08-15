# Architecture decision records

[← Documentation home](../README.md)

**Document type:** Current system architecture and ADR index  
**Status:** Current

Kingshot Alliance is an enterprise modular monolith organized by explicit business domains. This page is the repository-level current architecture entry point; it summarizes system shape and indexes durable decisions without replacing domain-owned contracts.

Governance: [Architecture and program-governance standard](../product/architecture-governance-standard.md) · [Cross-domain dependency map](../product/cross-domain-dependency-map.md) · [Shared glossary](../product/glossary.md) · [Current capability matrix](../product/current-capability-matrix.md)

## Current architecture view

```text
Browser / API client
        |
        v
  Nginx web entry point
        |
        v
  Laravel modular monolith
  - request/trace correlation
  - Identity authentication / MFA / password confirmation
  - active Player identity
  - Alliance / Kingdom scoped authorization
  - transactional mutation boundaries
        |
        +--------------------------+
        |                          |
        v                          v
Business domains             Shared/platform domains
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
 scheduler / listeners ----------> Horizon integrations queue ----------> signed webhooks
```

### Runtime topology

One immutable application image supplies all Laravel runtime roles. In Azure Container Apps, each web replica contains a tightly coupled Nginx container on port 8080 and PHP-FPM container on port 9000; Nginx reaches PHP-FPM through replica-local `127.0.0.1:9000`. Horizon runs as a separate Container App, while scheduler execution and migrations run as Container Apps Jobs so those lifecycles do not scale with web traffic. Docker Compose retains service-name FastCGI routing through `app:9000`. Production Content media requires durable S3-backed storage.

See [ADR 0009](0009-azure-container-apps-runtime-topology.md), [Runtime configuration](../operations/configuration-reference.md), [Background processing](../operations/background-processing.md), [Observability](../operations/observability.md), and [Deployment](../operations/runbooks/deployment.md).

## Domain ownership

Runtime PHP is domain-first under `app/Domain/<Domain>` with exactly 14 canonical roots:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, `Recruitment`.

Living documentation mirrors those roots under `docs/domains/<domain>/`. The [domain index](../domains/README.md) is the canonical owner navigation. The [cross-domain dependency map](../product/cross-domain-dependency-map.md) records supported collaboration direction; the [domain boundary audit](../product/domain-boundary-audit.md) records high-risk ownership evidence.

Key ownership boundaries:

- Identity owns global User/authentication/MFA assurance; identity alone does not grant game-domain authority.
- Kingdoms owns durable Player identity and neutral Kingdom/game-Alliance references plus Alliance-owned roster/history/intelligence/transfer/diplomacy workflows.
- Alliances owns the platform Alliance aggregate and Alliance lifecycle context.
- Memberships owns Player-based Alliance membership/invitation lifecycle.
- Authorization owns Alliance and Kingdom roles/permissions/effective authority contracts.
- Audit owns attributable evidence, not authorization.
- Content owns authored/public/member content and media.
- Events owns Event schedules/occurrences/participation/results and durable historical Event facts across Player, Alliance, and Kingdom targets.
- Rallies owns Rally guidance/formations/groups/assignments/Rally participation.
- Notifications owns durable reminder and scheduled-report due-time coordination.
- Recruitment owns candidate/application/onboarding/retention workflow.
- Contributions owns contribution/calculation/reporting/export state and composes Events facts into unified contribution history without taking ownership of Event facts.
- Integrations owns API credentials/read contracts and webhook subscription/signing/delivery/retry state.
- Platform owns true cross-tenant administration/lifecycle/entitlements/retention and transactional-outbox infrastructure.

Cross-domain collaboration uses intentional supported contracts rather than accidental persistence reach-through. Bidirectional workflow collaboration is allowed when ownership remains explicit.

Every domain owns its own mutation orchestration and persistence semantics. Repository-wide transactional/concurrency principles are shared through [ADR 0010](0010-transactional-mutation-authority.md); the ADR standardizes how current authority/state is re-established, how locks are chosen and ordered, and when constraints/CAS are preferred without forcing unrelated domains into one mutation framework.

Historical Event and contribution ownership follows [ADR 0011](0011-event-history-and-contribution-ownership.md): Player history follows durable `player_id`; Alliance/Kingdom organizational history follows the immutable Event target; current authority controls access but current membership never rewrites historical ownership.

## Tenancy and Kingdoms reference boundaries

Identity is global. Game-domain authority derives from the active Player. Alliance-scoped access requires that active Player to hold the applicable active Player membership/rank/role; Kingdom-scoped authority requires exact-Kingdom Player role assignment. Platform administration is a distinct User-scoped grant and is not a game-domain role.

Kingdoms deliberately separates neutral reference identity from tenant-owned observations/workflows. Sharing a `Kingdom`, `Player`, or `KingdomAlliance` reference never grants access to another Alliance's roster, notes, snapshots, imports, transfer plans, observations, diplomacy, contacts, or derived intelligence.

Use [the glossary](../product/glossary.md) for the platform `Alliance` versus game-side `KingdomAlliance` distinction and other shared terminology.

## Synchronous, asynchronous, and external boundaries

Normal first-party requests execute synchronously inside the modular monolith and use domain-owned actions/services/models.

State-changing actions follow the transactional mutation principles in ADR 0010: current authority/state is re-established inside the write transaction; only the narrowest natural aggregate/state rows required by the invariant are locked; database constraints or atomic compare-and-set transitions are preferred where they express the invariant; and unbounded external I/O occurs only after durable state/claim is committed.

Platform owns the transactional outbox as durable asynchronous infrastructure. Producer domains own business transition/payload semantics. Notifications and other consumers coordinate first-party downstream effects; Integrations owns external machine boundaries.

An internal outbox event is not automatically an externally eligible webhook event. All `kingdoms.*` events remain excluded from generic public webhook fan-out. The current `/api/v1` surface remains limited to approved read contracts and has no Kingdoms roster/snapshot/intelligence/transfer/diplomacy scope.

See [ADR 0004](0004-queues-and-transactional-outbox.md), [ADR 0010](0010-transactional-mutation-authority.md), and [Integrations](../domains/integrations/README.md).

## Data, trust, and production boundaries

- **PostgreSQL** — relational application state, tenant business records, global neutral Kingdoms references, transactional invariants, and row-level mutation coordination.
- **Redis** — cache, encrypted sessions, queues, Horizon coordination; Redis locks are not a correctness substitute for PostgreSQL invariants.
- **Private object/file storage** — Content media/generated artifacts where applicable.
- **Audit** — attributable security/business evidence.
- **Transactional outbox** — durable asynchronous work before publication.

External API credentials are Alliance-bound and read-only with fixed scopes. Outbound webhooks are signed/retried. Application URL validation is not a production egress/SSRF boundary; hosted infrastructure must enforce network policy separately.

Runtime provides JSON stderr logging, request IDs, W3C trace correlation, liveness/readiness, Horizon/outbox/webhook visibility, and repository-controlled launch-health signals. No OpenTelemetry exporter is configured in-repository; Laravel Pulse recording remains disabled pending schema/access policy.

Repository automation proves repository-controlled hardening but not real production ingress/TLS, egress, alert ownership, capacity, operator identity, managed dependencies, or production-managed key/media recovery. Real production launch remains **not yet approved** under [Production launch approval](../product/production-launch-approval.md).

## ADR lifecycle

Allowed ADR states are exactly:

- **Proposed** — decision is under review and is not architecture authority yet.
- **Accepted** — decision is current architecture authority.
- **Superseded** — decision was once accepted but has been replaced by another ADR.
- **Rejected** — proposal was considered and intentionally not adopted.

Accepted/superseded ADR rationale is historical decision evidence. Do not silently rewrite it to match later architecture. A replacing ADR names the superseded decision, and the old ADR links to its replacement.

Use [the ADR template](adr-template.md) for new material decisions. Architecture decisions cannot silently expand unapproved product scope.

## Decision index

| ADR | Decision | Status |
| --- | --- | --- |
| [0001](0001-modular-monolith.md) | Enterprise modular monolith | Accepted |
| [0002](0002-alliance-level-tenancy.md) | Alliance-level tenancy | Accepted |
| [0003](0003-first-party-authentication.md) | First-party authentication | Accepted |
| [0004](0004-queues-and-transactional-outbox.md) | Queues and transactional outbox | Accepted |
| [0005](0005-s3-compatible-object-storage.md) | S3-compatible object storage | Accepted |
| [0006](0006-observability-and-correlation.md) | Observability and correlation | Accepted |
| [0007](0007-testing-toolchain-compatibility.md) | Testing toolchain compatibility | Accepted |
| [0008](0008-domain-first-source-layout.md) | Domain-first source layout | Accepted |
| [0009](0009-azure-container-apps-runtime-topology.md) | Azure Container Apps runtime topology | Accepted |
| [0010](0010-transactional-mutation-authority.md) | Transactional mutation and concurrency principles | Accepted |
| [0011](0011-event-history-and-contribution-ownership.md) | Historical Event and contribution ownership | Accepted |

There are currently no Superseded or Rejected numbered ADRs.

## Canonical physical structure

```text
app/Domain/<14 canonical domains>/

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

See [Repository structure audit](../product/repository-structure-audit.md). Architecture documentation must not introduce parallel source/docs/test groupings that conflict with the accepted implementation plan, documentation standard, ADRs, or architecture tests.
