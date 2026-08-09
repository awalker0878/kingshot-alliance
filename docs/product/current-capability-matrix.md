# Current capability matrix

[← Product and program documentation](README.md)

**Status:** Current

This matrix summarizes capabilities implemented in the current Phase 0–6-complete runtime and accepted post-program increments, and separately identifies explicit non-capabilities/boundaries. It is a navigation aid, not a replacement for the baseline implementation plan, approved product-increment scopes, accepted ADRs, living domain guides, or code/tests.

Code and tests remain authoritative for exact runtime behavior. The [implementation plan](implementation-plan.md) remains authoritative for the completed Phase 0–6 baseline; approved post-program scope is recorded in named increment documents such as [`KINGDOMS-001`](kingdoms-roster-intelligence-increment.md), with acceptance evidence in its [exit report](kingdoms-roster-intelligence-exit-report.md). A real production cutover remains **not yet approved**; see [production launch approval](production-launch-approval.md).

## Implemented product capabilities

| Capability | Current state | Primary ownership | Living contract |
| --- | --- | --- | --- |
| Global accounts and authentication | Implemented | Identity | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
| Verified-email and MFA-backed privileged access | Implemented | Identity, Authorization, Platform | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md), [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Multi-alliance tenancy and active-alliance context | Implemented | Alliances, Memberships | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
| Alliance membership, invitations, built-in roles, and RBAC | Implemented | Memberships, Authorization | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
| First-class Kingdom reference and Alliance association | **Accepted by `KINGDOMS-001`** | Kingdoms, Alliances | [Kingdoms](../domains/kingdoms.md) |
| Neutral Kingshot player identity and alliance-owned roster | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms roster](../domains/kingdoms-roster.md) |
| Append-only player snapshots and current/stale/missing projection | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms player snapshots](../domains/kingdoms-snapshots.md) |
| Roster totals, data-quality indicators and bounded 7/30-day intelligence | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms roster intelligence](../domains/kingdoms-intelligence.md) |
| Controlled roster CSV preview/confirmation and safe exports | **Accepted by `KINGDOMS-001`** | Kingdoms | [Kingdoms controlled CSV migration](../domains/kingdoms-csv-migration.md) |
| Public alliance presence and managed content | Implemented | Content | [Content management](../domains/content-management.md) |
| Content revisions, visibility, scheduling, and private media | Implemented | Content | [Content management](../domains/content-management.md) |
| Events, recurrence, registration, waitlisting, and attendance | Implemented | Events | [Events and rallies](../domains/events-and-rallies.md) |
| Rally guidance, formations, assignments, and participation | Implemented | Rallies | [Events and rallies](../domains/events-and-rallies.md) |
| Event reminders | Implemented as durable in-app/outbox coordination | Notifications, Events | [Notifications](../domains/notifications.md) |
| Recruitment intake, candidate pipeline, review, decisions, onboarding, and retention | Implemented | Recruitment | [Recruitment](../domains/recruitment.md) |
| Contribution records, calculations, corrections, reporting, and exports | Implemented | Contributions | [Contributions and reporting](../domains/contributions-and-reporting.md) |
| Scheduled contribution-report requests | Implemented as durable scheduler/outbox coordination | Notifications, Contributions | [Notifications](../domains/notifications.md), [Contributions and reporting](../domains/contributions-and-reporting.md) |
| Read-only alliance API credentials | Implemented | Integrations | [Integrations](../domains/integrations.md) |
| Read-only API access for alliance, events, and contributions | Implemented | Integrations | [Integrations](../domains/integrations.md) |
| Signed outbound webhooks with retries | Implemented for externally eligible events | Integrations | [Integrations](../domains/integrations.md) |
| Cross-tenant platform administration | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Alliance lifecycle administration | Implemented: provision, suspend, close, logical delete, restore, export, ownership transfer | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Plans, entitlements, tenant settings, and feature flags | Implemented as payment-independent platform controls | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Legal holds, account deletion, and operational retention | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Usage snapshots and platform operational visibility | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md), [Observability](../operations/observability.md) |
| Scheduled/background processing | Implemented | Content, Notifications, Integrations, Recruitment, Platform | [Background processing](../operations/background-processing.md) |
| Transactional outbox | Implemented | Platform | [Background processing](../operations/background-processing.md), [ADR 0004](../adr/0004-queues-and-transactional-outbox.md) |
| Liveness/readiness and request/trace correlation | Implemented | Platform | [Observability](../operations/observability.md) |
| Immutable-image deployment, staging validation, backup/restore tooling, and rollback procedures | Implemented repository controls | Operations | [Operations index](../operations/README.md) |

## Accepted post-program increments

| Scope | Status | Accepted outcome | Evidence |
| --- | --- | --- | --- |
| `KINGDOMS-001` — Kingdoms roster intelligence | **Accepted** | First-class Kingdom/game-player model, alliance-owned roster, append-only snapshots, derived roster intelligence, controlled CSV migration/export, cross-slice security/accessibility/query/rollback hardening | [Scope](kingdoms-roster-intelligence-increment.md), [implementation plan](kingdoms-roster-intelligence-implementation-plan.md), [exit report](kingdoms-roster-intelligence-exit-report.md) |

Candidate transfer planning, kingdom-alliance/diplomacy intelligence, automated game-data ingestion, opt-in cross-alliance intelligence, automated player scoring and public Kingdoms API/webhook contracts remain follow-on candidates only. They are **not approved implementation scope** until each receives its own increment record.

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| `KINGDOMS-001` | **Accepted / implemented** | `K1-P1`–`K1-P5` deliver the complete runtime capability and `K1-P6` closes domain/security/accessibility/migration/query/operations/integration acceptance. |
| Global Kingdom / `KingdomPlayer` identity | **Neutral reference data** | Shared reference identity never grants cross-alliance access to roster state, private notes, membership links, snapshots, imports, exports or metrics. |
| Legacy free-form alliance kingdom storage | **Removed** | Alliance persistence uses `kingdom_id`; existing presentation/API `kingdom` values are derived from the canonical relation rather than a compatibility column. |
| Kingdoms external API | **Not approved / not implemented** | `/api/v1` remains limited to documented alliance/events/contributions reads; no roster/snapshot/intelligence API scope or route exists. |
| Kingdoms external webhooks | **Not approved / not implemented** | `alliance.kingdom_updated` and `kingdoms.*` are internal outbox events and are excluded from generic webhook fan-out, including wildcard subscriptions. |
| Automated Kingshot game-data ingestion | **Not approved / not implemented** | `KINGDOMS-001` is manual/import first. Scraping, OCR, bots, and undocumented/unapproved APIs are explicitly outside its scope. |
| Transfer planning and kingdom diplomacy | **Roadmap candidates / not approved** | These may follow the roster foundation but require separate product increment approval. |
| Cross-alliance Kingdoms rankings/intelligence | **Not approved / not implemented** | No cross-alliance player ranking, punitive scoring or shared roster intelligence is part of `KINGDOMS-001`. |
| Payment processing / billing | **Not implemented** | Plans and entitlements exist, but there is no payment-processing workflow. |
| Support impersonation | **Not implemented** | Platform administrators do not receive an impersonation capability. |
| Generic email/SMS/push notification provider | **Not implemented as a Notifications-domain transport** | Current Notifications behavior coordinates in-app reminder/report requests through persisted state and the transactional outbox. |
| Public webhook event catalog/schema version | **Not currently centralized** | The webhook envelope/signature contract is documented; externally eligible event types remain constrained by the Integrations boundary and event-specific payloads are not governed by a separate schema registry/version field. |
| Laravel Pulse recording | **Disabled** | Pulse is present as a foundation but hosted configuration requires recording to remain disabled until its schema/access policy is introduced. |
| OpenTelemetry exporter | **Not configured in-repository** | Request/trace correlation exists, but there is no repository-configured OTEL exporter. |
| Real production launch | **Not yet approved** | Repository hardening and `KINGDOMS-001` product acceptance do not prove external production infrastructure/operational evidence required before cutover approval. |

## How to use this matrix

Use the implemented-capability table to answer “is this available in the accepted product?” Use the accepted-increment table to trace post-program acceptance evidence. Use explicit boundaries to answer what is deliberately **not** part of the runtime.

For architectural reasoning, use the [architecture decisions and current architecture view](../adr/README.md). For operational behavior, use the [operations index](../operations/README.md). For security requirements and current launch-security evidence, use the [security index](../security/README.md).

Historical phase and increment slice reports remain acceptance/implementation evidence. They are not a changelog and should not be rewritten into release notes or user onboarding. Real production launch remains a separate approval decision.
