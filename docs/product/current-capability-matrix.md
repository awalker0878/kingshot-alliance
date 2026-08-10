# Current capability matrix

[← Product and program documentation](README.md)

**Status:** Current

This matrix summarizes capabilities implemented in the current Phase 0–6-complete runtime and accepted post-program increments, and identifies explicit non-capabilities/boundaries. It is a navigation aid, not a replacement for the baseline implementation plan, approved product-increment scopes, accepted ADRs, living domain contracts, or code/tests.

Code and tests remain authoritative for exact runtime behavior. The [implementation plan](implementation-plan.md) remains authoritative for the completed Phase 0–6 baseline. Accepted post-program implementation evidence is recorded for [`KINGDOMS-001`](kingdoms-roster-intelligence-exit-report.md), [`KINGDOMS-002`](kingdoms-transfer-planning-exit-report.md), and [`KINGDOMS-003`](kingdoms-alliance-intelligence-exit-report.md). A real production cutover remains **not yet approved**; see [production launch approval](production-launch-approval.md).

## Implemented product capabilities

| Capability | Current state | Primary ownership | Living contract |
| --- | --- | --- | --- |
| Global accounts and authentication | Implemented | Identity | [Identity](../domains/identity/README.md) |
| Verified-email and MFA-backed privileged access | Implemented | Identity, Authorization, Platform | [Identity](../domains/identity/README.md), [Authorization](../domains/authorization/README.md), [Platform](../domains/platform/README.md) |
| Multi-alliance tenancy and active-alliance context | Implemented | Alliances, Memberships | [Alliances](../domains/alliances/README.md), [Memberships](../domains/memberships/README.md) |
| Alliance membership, invitations, built-in roles, and RBAC | Implemented | Memberships, Authorization | [Memberships](../domains/memberships/README.md), [Authorization](../domains/authorization/README.md) |
| Attributable privileged/security activity audit | Implemented | Audit | [Audit](../domains/audit/README.md) |
| First-class Kingdom reference and Alliance association | **Accepted by `KINGDOMS-001`** | Kingdoms, Alliances | [Kingdoms](../domains/kingdoms/README.md), [Alliances](../domains/alliances/README.md) |
| Neutral Kingshot player identity and Alliance-owned roster | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms roster](../domains/kingdoms/roster.md) |
| Append-only player snapshots and current/stale/missing projection | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms player snapshots](../domains/kingdoms/snapshots.md) |
| Roster totals, data-quality indicators and bounded 7/30-day intelligence | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms roster intelligence](../domains/kingdoms/intelligence.md) |
| Controlled roster CSV preview/confirmation and safe exports | **Accepted by `KINGDOMS-001`** | Kingdoms | [Controlled CSV migration](../domains/kingdoms/csv-migration.md) |
| Alliance-owned transfer cycles and captured home-Kingdom lifecycle | **Accepted by `KINGDOMS-002`** | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Incoming/outgoing/staying transfer participants and destination planning | **Accepted by `KINGDOMS-002`** | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Transfer groups and same-Alliance coordinators | **Accepted by `KINGDOMS-002`** | Kingdoms, Memberships reference | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Manual readiness, blocker history and coordination summaries | **Accepted by `KINGDOMS-002`** | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Explicit idempotent transfer completion and accepted roster handoff | **Accepted by `KINGDOMS-002`** | Kingdoms | [Transfer planning](../domains/kingdoms/transfer-planning.md) |
| Neutral game-side Alliance identity and tenant-owned tracking | **Accepted by `KINGDOMS-003`** | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Append-oriented tracked-Alliance observations, correction/invalidation history and freshness projection | **Accepted by `KINGDOMS-003`** | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Explicit human-maintained diplomacy/NAP lifecycle and transition history | **Accepted by `KINGDOMS-003`** | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Manager-private diplomacy contacts with non-identity lifecycle | **Accepted by `KINGDOMS-003`** | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Descriptive game-Alliance intelligence dashboard and bounded factual trends | **Accepted by `KINGDOMS-003`** | Kingdoms | [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md) |
| Public Alliance presence and managed content | Implemented | Content | [Content](../domains/content/README.md) |
| Content revisions, visibility, scheduling, and private media | Implemented | Content | [Content](../domains/content/README.md) |
| Events, recurrence, registration, waitlisting, and Event attendance | Implemented | Events | [Events](../domains/events/README.md) |
| Rally guidance, formations, assignments, and Rally participation | Implemented | Rallies | [Rallies](../domains/rallies/README.md) |
| Event reminders | Implemented as durable in-app/outbox coordination | Notifications, Events | [Notifications](../domains/notifications/README.md), [Events](../domains/events/README.md) |
| Recruitment intake, candidate pipeline, review, decisions, onboarding, and retention | Implemented | Recruitment | [Recruitment](../domains/recruitment/README.md) |
| Contribution records, calculations, corrections, reporting, and exports | Implemented | Contributions | [Contributions](../domains/contributions/README.md) |
| Scheduled contribution-report requests | Implemented as durable scheduler/outbox coordination | Notifications, Contributions | [Notifications](../domains/notifications/README.md), [Contributions](../domains/contributions/README.md) |
| Read-only Alliance API credentials | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Read-only API access for Alliance, Events, and Contributions | Implemented | Integrations | [Integrations](../domains/integrations/README.md) |
| Signed outbound webhooks with retries | Implemented for externally eligible events | Integrations | [Integrations](../domains/integrations/README.md) |
| Cross-tenant platform administration | Implemented | Platform | [Platform](../domains/platform/README.md) |
| Alliance lifecycle administration | Implemented: provision, suspend, close, logical delete, restore, export, ownership transfer | Platform | [Platform](../domains/platform/README.md) |
| Plans, entitlements, tenant settings, and feature flags | Implemented as payment-independent platform controls | Platform | [Platform](../domains/platform/README.md) |
| Legal holds, account deletion, and operational retention | Implemented | Platform | [Platform](../domains/platform/README.md) |
| Usage snapshots and platform operational visibility | Implemented | Platform | [Platform](../domains/platform/README.md), [Observability](../operations/observability.md) |
| Scheduled/background processing | Implemented | Content, Notifications, Integrations, Recruitment, Platform | [Background processing](../operations/background-processing.md) |
| Transactional outbox | Implemented | Platform | [Platform](../domains/platform/README.md), [Background processing](../operations/background-processing.md), [ADR 0004](../adr/0004-queues-and-transactional-outbox.md) |
| Liveness/readiness and request/trace correlation | Implemented | Platform | [Observability](../operations/observability.md) |
| Immutable-image deployment, staging validation, backup/restore tooling, and rollback procedures | Implemented repository controls | Operations | [Operations index](../operations/README.md) |

## Post-program increments

| Scope | Status | Outcome | Evidence / plan |
| --- | --- | --- | --- |
| `KINGDOMS-001` — Kingdoms roster intelligence | **Accepted** | First-class Kingdom/game-player model, Alliance-owned roster, append-only snapshots, derived roster intelligence, controlled CSV migration/export, cross-slice security/accessibility/query/rollback hardening | [Scope](kingdoms-roster-intelligence-increment.md), [implementation plan](kingdoms-roster-intelligence-implementation-plan.md), [exit report](kingdoms-roster-intelligence-exit-report.md) |
| `KINGDOMS-002` — Transfer planning | **Accepted** | Alliance-owned transfer cycles, incoming/outgoing/staying intent, destinations, groups/coordinators, manual readiness/blockers, explicit idempotent roster handoff, cross-slice security/accessibility/query/rollback hardening | [Scope](kingdoms-transfer-planning-increment.md), [implementation plan](kingdoms-transfer-planning-implementation-plan.md), [exit report](kingdoms-transfer-planning-exit-report.md) |
| `KINGDOMS-003` — Kingdom/Alliance intelligence and diplomacy | **Accepted** | Neutral game-side Alliance identity/tracking, tenant-owned factual observation history, explicit diplomacy/NAP history, manager-private contacts and descriptive intelligence with whole-increment tenancy/privacy/rollback/query/integration hardening | [Scope](kingdoms-alliance-intelligence-increment.md), [implementation plan](kingdoms-alliance-intelligence-implementation-plan.md), [exit report](kingdoms-alliance-intelligence-exit-report.md), [accessibility](kingdoms-alliance-intelligence-accessibility.md) |

`KINGDOMS-003` is **Accepted** for repository/product purposes. Automated game-data ingestion, opt-in cross-Alliance/shared Kingdom intelligence, automated player/Alliance scoring, automatic diplomacy/transfer behavior and public Kingdoms API/webhook contracts remain separate follow-on scopes and are not implied by this acceptance.

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| `KINGDOMS-001` | **Accepted / implemented** | `K1-P1`–`K1-P5` deliver runtime capability and `K1-P6` closes domain/security/accessibility/migration/query/operations/integration acceptance. |
| `KINGDOMS-002` transfer planning | **Accepted / implemented** | `K2-P1`–`K2-P5` deliver transfer cycles, participants, groups/coordinators, manual readiness/blockers and explicit completion; `K2-P6` closes whole-increment acceptance. |
| `KINGDOMS-003` Alliance intelligence/diplomacy | **Accepted / implemented** | `K3-P1`–`K3-P5` deliver neutral game-Alliance tracking, factual observations/history, explicit diplomacy, private contacts and descriptive intelligence; `K3-P6` closes whole-increment acceptance. |
| Global Kingdom / `KingdomPlayer` identity | **Neutral reference data** | Shared reference identity never grants cross-Alliance access to roster state, private notes, membership links, snapshots, imports, exports, metrics or transfer-plan data. |
| Neutral `KingdomAlliance` identity | **Accepted by `KINGDOMS-003`** | Global neutral game-side Alliance reference only. Stable game Alliance ID within a Kingdom is the only automatic identity key; name/tag never auto-merge, and tenant tracking/private intelligence stays Alliance-owned. |
| K3 Alliance observations | **Accepted by `KINGDOMS-003`** | Tenant-owned manual factual history supports observed name/tag, optional power/member count, capture time, exact-retry idempotency, correction/invalidation preservation and current/stale/missing projection. It does not infer diplomacy or rank/threat. |
| K3 diplomacy/contacts/descriptive dashboard | **Accepted by `KINGDOMS-003`** | Diplomacy is explicit human-maintained state/history; contacts are manager-private coordination data without identity/authorization linkage; dashboard provides descriptive bounded factual trends and review/data-quality indicators only. |
| Legacy free-form Alliance Kingdom storage | **Removed** | Alliance persistence uses `kingdom_id`; presentation/API `kingdom` values derive from the canonical relation rather than a compatibility column. |
| Kingdoms external API | **Not approved / not implemented** | `/api/v1` remains limited to documented Alliance/Events/Contributions reads; no roster/snapshot/intelligence/transfer/diplomacy route/scope exists. |
| Kingdoms external webhooks | **Not approved / not implemented** | `alliance.kingdom_updated` and all `kingdoms.*` are internal outbox events excluded from generic webhook fan-out, including wildcard subscriptions. |
| Automated Kingshot game-data ingestion | **Not approved / not implemented** | Accepted observations are first-party/manual. Scraping, OCR, bots, automated game ingestion and undocumented/unapproved APIs remain outside runtime scope. |
| Cross-Alliance/shared Kingdom intelligence | **Not approved / not implemented** | Accepted K3 intelligence remains tenant-owned. Shared neutral references do not expose another platform Alliance's observations, diplomacy, contacts, notes/history or derived summaries. |
| Transfer marketplace / eligibility / resource optimization / automatic execution | **Not approved / not implemented** | Transfer planning is manual coordination only. There is no player ranking, inferred eligibility/readiness, pass/ticket optimization, bulk completion or automatic in-game transfer. |
| Alliance threat/rival/desirability scoring | **Not approved / not implemented** | K3 facts/trends contain no threat score, battle prediction, punitive ranking, automated diplomacy inference or automated recommendation. |
| Payment processing / billing | **Not implemented** | Plans and entitlements exist, but there is no payment-processing workflow. |
| Support impersonation | **Not implemented** | Platform administrators do not receive an impersonation capability. |
| Generic email/SMS/push notification provider | **Not implemented as Notifications transport** | Notifications coordinates durable in-app reminder/report requests through persisted state and transactional outbox. |
| Public webhook event catalog/schema version | **Not currently centralized** | Envelope/signature contract is documented; externally eligible event types remain constrained by Integrations and event-specific payloads are not governed by a separate schema registry/version field. |
| Laravel Pulse recording | **Disabled** | Pulse is present as a foundation but hosted configuration requires recording disabled until schema/access policy is introduced. |
| OpenTelemetry exporter | **Not configured in-repository** | Request/trace correlation exists, but no repository-configured OTEL exporter exists. |
| Real production launch | **Not yet approved** | Repository hardening and accepted product increments do not prove external production infrastructure/operational evidence required before cutover approval. |

## Documentation architecture

Living domain contracts are now deterministic folders under `docs/domains/`, mirrored one-to-one from `app/Domain/*`. See the [documentation standard](documentation-standard.md), [repository structure audit](repository-structure-audit.md), and [domain boundary audit](domain-boundary-audit.md).

## How to use this matrix

Use the implemented-capability table to answer “is this available in the current branch/runtime?” Use the post-program increment table to distinguish Accepted whole increments from future/unapproved scopes. Use explicit boundaries to answer what is deliberately **not** part of runtime.

For architecture, use the [ADR/current architecture view](../adr/README.md). For operations, use the [operations index](../operations/README.md). For security, use the [security index](../security/README.md).

Historical phase and increment slice reports remain acceptance/implementation evidence. They are not a changelog and should not be rewritten into release notes or user onboarding. Real production launch remains a separate approval decision.
