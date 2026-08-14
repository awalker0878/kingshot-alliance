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
  - Alliances active-tenant context
  - Memberships active membership
  - Authorization policy / permission checks
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

- Identity owns global User/authentication/MFA assurance; identity alone does not grant tenant access.
- Alliances owns the platform Alliance aggregate and active tenant context.
- Memberships owns membership/invitation lifecycle.
- Authorization owns Alliance roles/permissions/effective authority.
- Audit owns attributable evidence, not authorization.
- Content owns authored/public/member content and media.
- Events owns Event schedules/occurrences/registration/Event attendance.
- Rallies owns Rally guidance/formations/groups/assignments/Rally participation.
- Notifications owns durable reminder and scheduled-report due-time coordination.
- Recruitment owns candidate/application/onboarding/retention workflow.
- Contributions owns contribution/calculation/reporting/export state.
- Integrations owns API credentials/read contracts and webhook subscription/signing/delivery/retry state.
- Platform owns cross-tenant administration/lifecycle/entitlements/retention and transactional-outbox infrastructure.
- Kingdoms owns neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/intelligence/transfer/diplomacy workflows.

Cross-domain collaboration uses intentional supported contracts rather than accidental persistence reach-through. Bidirectional workflow collaboration is allowed when ownership remains explicit.

## Tenancy and Kingdoms reference boundaries

Identity is global. Normal Alliance-scoped access requires an explicit active Alliance, active Memberships-owned membership, and applicable Authorization-owned permission checks. Platform administration is a distinct cross-tenant grant and is not an Alliance role.

Kingdoms deliberately separates neutral reference identity from tenant-owned observations/workflows. Sharing a `Kingdom`, `Player`, or `KingdomAlliance` reference never grants access to another Alliance's roster, notes, snapshots, imports, transfer plans, observations, diplomacy, contacts, or derived intelligence.

Use [the glossary](../product/glossary.md) for the platform `Alliance` versus game-side `KingdomAlliance` distinction and other shared terminology.

## Synchronous, asynchronous, and external boundaries

Normal first-party requests execute synchronously inside the modular monolith and use domain-owned actions/services/models.

Platform owns the transactional outbox as durable asynchronous infrastructure. Producer domains own business transition/payload semantics. Notifications and other consumers coordinate first-party downstream effects; Integrations owns external machine boundaries.

An internal outbox event is not automatically an externally eligible webhook event. All `kingdoms.*` events remain excluded from generic public webhook fan-out. The current `/api/v1` surface remains limited to approved read contracts and has no Kingdoms roster/snapshot/intelligence/transfer/diplomacy scope.

See [ADR 0004](0004-queues-and-transactional-outbox.md) and [Integrations](../domains/integrations/README.md).

## Data, trust, and production boundaries

- **PostgreSQL** — relational application state, tenant business records, and global neutral Kingdoms references.
- **Redis** — cache, encrypted sessions, queues, Horizon coordination.
- **Private object/file storage** — Content media/generated artifacts where applicable.
- **Audit** — attributable security/business evidence.
- **Transactional outbox** — durable asynchronous work before publication.

External API credentials are Alliance-bound and read-only with fixed scopes. Outbound webhooks are signed/retried. Application URL validation is not a production egress/SSRF boundary; hosted infrastructure must enforce network policy separately.

Runtime provides JSON stderr logging, request IDs, W3C trace correlation, liveness/readiness, Horizon/outbox/webhook visibility, and repository-controlled launch-health signals. No OpenTelemetry exporter is configured in-repository; Laravel Pulse recording remains disabled pending schema/access policy.

Repository automation proves repository-controlled hardening but not real production ingress/TLS, egress, alert ownership, capacity, operator identity, managed dependencies, or production-managed key/media recovery. Real production launch remains **not yet approved** under [Production launch approval](../product/production-launch-approval.md).

## ADR lifecycle

Allowed ADR states are exactly:

- **Proposed** — under review; not architecture authority yet.
- **Accepted** — current architecture authority.
- **Superseded** — formerly accepted, replaced by a named ADR.
- **Rejected** — considered but intentionally not adopted.

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