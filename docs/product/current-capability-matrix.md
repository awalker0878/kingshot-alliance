# Current capability matrix

[← Product and program documentation](README.md)

**Document type:** Current product/system navigation  
**Status:** Current  
**Architecture governance:** `DCP-P6`

This matrix is the repository-wide navigation surface for implemented capability and explicit non-capability. It identifies primary ownership and points to canonical living contracts; it is not a replacement for domain specifications.

System context: [Current architecture / ADR index](../adr/README.md) · [Cross-domain dependency map](cross-domain-dependency-map.md) · [Shared glossary](glossary.md) · [Domain boundary audit](domain-boundary-audit.md)

Code and tests remain authoritative for exact runtime. The [implementation plan](implementation-plan.md) remains authoritative for the completed Phase 0–6 baseline. A real production cutover remains **not yet approved** under [Production launch approval](production-launch-approval.md).

## Implemented capabilities

| Capability | Current state | Primary ownership | Living contract |
| --- | --- | --- | --- |
| Global accounts, authentication, verified email, password/session security and MFA | Implemented | Identity | [Identity](../domains/identity/README.md) |
| Multi-Alliance tenancy, Alliance creation/settings and active tenant context | Implemented | Alliances | [Alliances](../domains/alliances/README.md) |
| Membership and invitation lifecycle | Implemented | Memberships | [Memberships](../domains/memberships/README.md) |
| Alliance roles, permissions and permission evaluation | Implemented | Authorization | [Authorization](../domains/authorization/README.md) |
| Attributable privileged/security activity audit | Implemented | Audit | [Audit](../domains/audit/README.md) |
| Public/member authored content, revisions, publication and private media | Implemented | Content | [Content](../domains/content/README.md) |
| Event schedules, recurrence, registration/waitlisting and Event attendance | Implemented | Events | [Events](../domains/events/README.md) |
| Rally guidance, formations, groups, assignments and Rally participation | Implemented | Rallies | [Rallies](../domains/rallies/README.md) |
| Durable in-app Event reminder coordination | Implemented | Notifications + Events | [Notifications](../domains/notifications/README.md), [Events](../domains/events/README.md) |
| Recruitment intake, candidate pipeline, review, decisions, onboarding and retention | Implemented | Recruitment | [Recruitment](../domains/recruitment/README.md) |
| Contribution records, calculations, corrections, reporting and exports | Implemented | Contributions | [Contributions](../domains/contributions/README.md) |
| Scheduled Contribution-report request coordination | Implemented | Notifications + Contributions | [Notifications](../domains/notifications/README.md), [Contributions](../domains/contributions/README.md) |
| Read-only Alliance API credentials and Alliance/Events/Contributions API reads | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Signed outbound webhooks with retries for externally eligible events | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Cross-tenant administration, Alliance lifecycle, plans/entitlements, legal holds, retention, usage and outbox infrastructure | Implemented | Platform | [Platform](../domains/platform/README.md) |
| First-class Kingdom reference and Alliance→Kingdom association | Accepted (`KINGDOMS-001`) | Kingdoms + Alliances | [Kingdoms](../domains/kingdoms/README.md), [Alliances](../domains/alliances/README.md) |
| Neutral Kingshot player identity and Alliance-owned roster | Accepted (`KINGDOMS-001`) | Kingdoms | [Roster](../domains/kingdoms/roster.md) |
| Append-oriented player snapshots and current/stale/missing projection | Accepted (`KINGDOMS-001`) | Kingdoms | [Snapshots](../domains/kingdoms/snapshots.md) |
| Exact roster aggregates, data-quality/linkage/movement metrics and bounded 7/30-day trends | Accepted (`KINGDOMS-001`) | Kingdoms | [Roster intelligence](../domains/kingdoms/intelligence.md) |
| Controlled roster CSV dry-run/confirmation and safe exports | Accepted (`KINGDOMS-001`) | Kingdoms | [CSV migration](../domains/kingdoms/csv-migration.md) |
| Alliance-owned transfer cycles, participants, destinations, groups/coordinators and manual readiness/blockers | Accepted (`KINGDOMS-002`) | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Explicit idempotent transfer completion and roster handoff | Accepted (`KINGDOMS-002`) | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Neutral game-side Alliance identity/tracking and factual observations/corrections | Accepted (`KINGDOMS-003`) | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Explicit human-maintained diplomacy/NAP history and manager-private contacts | Accepted (`KINGDOMS-003`) | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Descriptive game-Alliance intelligence and bounded factual trends | Accepted (`KINGDOMS-003`) | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Scheduled/background processing, transactional outbox, health/readiness and request/trace correlation | Implemented | Platform + source/consumer domains | [Background processing](../operations/background-processing.md), [Observability](../operations/observability.md) |
| Immutable-image deployment, staging validation, backup/restore tooling and rollback procedures | Implemented repository controls | Shared Operations | [Operations](../operations/README.md) |

## Accepted domain increments

Detailed K1–K3 evidence is Kingdoms-owned:

| Scope | Status | Domain evidence |
| --- | --- | --- |
| `KINGDOMS-001` — roster intelligence | **Accepted** | [Product evidence](../domains/kingdoms/product/README.md), [security](../domains/kingdoms/security/README.md), [operations](../domains/kingdoms/operations/README.md), [testing](../domains/kingdoms/testing/README.md) |
| `KINGDOMS-002` — transfer planning | **Accepted** | [Product evidence](../domains/kingdoms/product/README.md), [security](../domains/kingdoms/security/README.md), [operations](../domains/kingdoms/operations/README.md), [testing](../domains/kingdoms/testing/README.md) |
| `KINGDOMS-003` — Alliance intelligence and diplomacy | **Accepted** | [Product evidence](../domains/kingdoms/product/README.md), [security](../domains/kingdoms/security/README.md), [operations](../domains/kingdoms/operations/README.md), [testing](../domains/kingdoms/testing/README.md) |

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| Global `Kingdom`, `KingdomPlayer`, `KingdomAlliance` identity | Neutral reference data | Shared reference identity never grants cross-Alliance access to tenant-owned roster/history/transfer/diplomacy/contacts/notes/intelligence. |
| Platform `Alliance` versus game-side `KingdomAlliance` | Distinct concepts | `Alliance` is the application tenant; `KingdomAlliance` is a neutral Kingdoms reference. See [glossary](glossary.md). |
| Legacy free-form Alliance Kingdom storage | Removed | Alliance persistence uses `kingdom_id`; display/API representation derives from the canonical relation. |
| Kingdoms external API | **Not approved / not implemented** | `/api/v1` remains limited to documented Alliance/Events/Contributions reads; no roster/snapshot/intelligence/transfer/diplomacy route/scope exists. |
| Kingdoms external webhooks | **Not approved / not implemented** | `alliance.kingdom_updated` and all `kingdoms.*` events remain internal and excluded from generic webhook fan-out. |
| Automated Kingshot game-data ingestion | **Not approved / not implemented** | No scraping, OCR, bots, automated game ingestion, or undocumented/unapproved APIs. |
| Cross-Alliance/shared Kingdom intelligence | **Not approved / not implemented** | Accepted Kingdoms intelligence remains tenant-owned. |
| Transfer marketplace, inferred eligibility/resource optimization or automatic execution | **Not approved / not implemented** | Transfer planning is explicit human coordination only. |
| Alliance/player threat/desirability/punitive scoring or automated recommendations | **Not approved / not implemented** | Accepted intelligence is descriptive/factual. |
| Payment processing/billing | **Not implemented** | Plans/entitlements exist without payment-processing workflow. |
| Support impersonation | **Not implemented** | Platform administrators receive no impersonation capability. |
| Generic email/SMS/push provider owned by Notifications | **Not implemented** | Notifications coordinates persisted in-app reminder/report-request work rather than generic transport. |
| Centralized public webhook event schema registry/version | **Not currently implemented** | Integrations defines envelope/signature/eligibility; producers own event-specific payload semantics. |
| Laravel Pulse recording | **Disabled** | Foundation exists; hosted recording remains disabled until schema/access policy is introduced. |
| OpenTelemetry exporter | **Not configured in-repository** | Request/trace correlation exists without repository-configured OTEL export. |
| Repository-controlled production hardening | **Accepted** | CI/build/staging/recovery/scan controls are demonstrated in repository evidence. |
| Real production launch | **Not yet approved** | Repository evidence does not prove deployed ingress/egress/secrets/operators/alerts/capacity/managed dependency recovery. |

## Architecture and ownership navigation

Use the narrowest owner:

- [Current architecture and ADR index](../adr/README.md) — system shape and durable decisions.
- [Cross-domain dependency map](cross-domain-dependency-map.md) — consumer→owner supported collaboration.
- [Shared glossary](glossary.md) — terminology whose ambiguity changes system meaning.
- [Domain index](../domains/README.md) — current domain contracts plus security/operations/interfaces/testing profiles.
- [Repository structure audit](repository-structure-audit.md) — physical conformance evidence.
- [Domain boundary audit](domain-boundary-audit.md) — semantic ownership evidence.
- [Security](../security/README.md) and [Operations](../operations/README.md) — shared cross-domain policy/runtime.

Domain-specific product/security/operations/interface/testing evidence belongs under the owning `docs/domains/<domain>/` tree. Top-level product/security/operations material stays cross-domain/program-wide.
