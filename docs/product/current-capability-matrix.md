# Current capability matrix

[← Product and program documentation](README.md)

**Status:** Current

This matrix summarizes the capabilities implemented in the current Phase 0–6-complete runtime and separately identifies approved roadmap scope that is not yet implemented. It is a navigation aid, not a replacement for the baseline implementation plan, approved product-increment scopes, accepted ADRs, living domain guides, or code/tests.

Code and tests remain authoritative for exact runtime behavior. The [implementation plan](implementation-plan.md) remains authoritative for the completed Phase 0–6 baseline; approved post-program scope is recorded in named increment documents such as [`KINGDOMS-001`](kingdoms-roster-intelligence-increment.md). A real production cutover remains **not yet approved**; see [production launch approval](production-launch-approval.md).

## Implemented product capabilities

| Capability | Current state | Primary ownership | Living contract |
| --- | --- | --- | --- |
| Global accounts and authentication | Implemented | Identity | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
| Verified-email and MFA-backed privileged access | Implemented | Identity, Authorization, Platform | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md), [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Multi-alliance tenancy and active-alliance context | Implemented | Alliances, Memberships | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
| Alliance membership, invitations, built-in roles, and RBAC | Implemented | Memberships, Authorization | [Identity, tenancy, and membership](../domains/identity-tenancy-and-membership.md) |
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
| Signed outbound webhooks with retries | Implemented | Integrations | [Integrations](../domains/integrations.md) |
| Cross-tenant platform administration | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Alliance lifecycle administration | Implemented: provision, suspend, close, logical delete, restore, export, ownership transfer | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Plans, entitlements, tenant settings, and feature flags | Implemented as payment-independent platform controls | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Legal holds, account deletion, and operational retention | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md) |
| Usage snapshots and platform operational visibility | Implemented | Platform | [Platform scale and administration](../domains/platform-scale-and-administration.md), [Observability](../operations/observability.md) |
| Scheduled/background processing | Implemented | Content, Notifications, Integrations, Recruitment, Platform | [Background processing](../operations/background-processing.md) |
| Transactional outbox | Implemented | Platform | [Background processing](../operations/background-processing.md), [ADR 0004](../adr/0004-queues-and-transactional-outbox.md) |
| Liveness/readiness and request/trace correlation | Implemented | Platform | [Observability](../operations/observability.md) |
| Immutable-image deployment, staging validation, backup/restore tooling, and rollback procedures | Implemented repository controls | Operations | [Operations index](../operations/README.md) |

## Approved roadmap scope — not yet implemented

Approved scope is not current runtime capability. These rows exist so readers can distinguish an authorized next increment from an unapproved idea.

| Scope | Status | Planned outcome | Authoritative scope |
| --- | --- | --- | --- |
| `KINGDOMS-001` — Kingdoms roster intelligence | **Approved — implementation not started** | First-class Kingdom model, separate game-player identity, alliance roster entries, historical snapshots, manual/CSV workflows, roster dashboard/intelligence, `kingdoms.manage`, audit/outbox and tenant-isolation controls | [Kingdoms roster intelligence product increment](kingdoms-roster-intelligence-increment.md) |

Candidate transfer planning, kingdom-alliance intelligence, automated game-data ingestion and opt-in cross-alliance intelligence are listed as follow-on roadmap candidates inside `KINGDOMS-001`; they are **not approved implementation scope** until each receives its own increment record.

## Explicit current boundaries

| Area | Current state | Meaning |
| --- | --- | --- |
| `Kingdoms` runtime capability | **Approved roadmap scope / not implemented** | `KINGDOMS-001` is approved, but `app/Domain/Kingdoms` still has no runtime PHP and the current alliance model still uses the existing kingdom field until the increment is implemented and accepted. |
| Automated Kingshot game-data ingestion | **Not approved / not implemented** | `KINGDOMS-001` is manual/import first. Scraping, OCR, bots, and undocumented/unapproved APIs are explicitly outside its scope. |
| Transfer planning and kingdom diplomacy | **Roadmap candidates / not approved** | These may follow the roster foundation but require separate product increment approval. |
| Payment processing / billing | **Not implemented** | Plans and entitlements exist, but there is no payment-processing workflow. |
| Support impersonation | **Not implemented** | Platform administrators do not receive an impersonation capability. |
| Generic email/SMS/push notification provider | **Not implemented as a Notifications-domain transport** | Current Notifications behavior coordinates in-app reminder/report requests through persisted state and the transactional outbox. |
| Public webhook event catalog/schema version | **Not currently centralized** | The webhook envelope/signature contract is documented, but event-specific payloads are not governed by a separate public schema registry/version field. |
| Laravel Pulse recording | **Disabled** | Pulse is present as a foundation but hosted configuration requires recording to remain disabled until its schema/access policy is introduced. |
| OpenTelemetry exporter | **Not configured in-repository** | Request/trace correlation exists, but there is no repository-configured OTEL exporter. |
| Real production launch | **Not yet approved** | Repository hardening is accepted; external infrastructure/operational evidence remains required before production cutover approval. |

## How to use this matrix

Use the implemented-capability table to answer “is this available in the current product?” Use the approved-roadmap table to answer “is this authorized next scope?” and then follow the scope document for boundaries and acceptance criteria.

For architectural reasoning, use the [architecture decisions and current architecture view](../adr/README.md). For operational behavior, use the [operations index](../operations/README.md). For security requirements and current launch-security evidence, use the [security index](../security/README.md).

Historical phase exit reports remain acceptance evidence for how baseline capabilities were delivered. They are not a changelog and should not be rewritten to describe the current combined product or future increments.
