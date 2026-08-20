# Capability catalogue

Status: Current

This is the user/product view of implemented capability groups. Architectural ownership is linked where useful.

| Product capability | Outcome | Architectural owner |
| --- | --- | --- |
| Account security | Register, authenticate, verify email, manage profile/password/MFA/recovery. | Accounts |
| Player context | Own/claim Players and operate as one active game persona at a time. | GameWorld/Players; workflows coordinate cross-context effects |
| Gift Codes | Share sourced codes, prepare official redemption for one or all Governors, and track per-Governor outcomes. | GameWorld/GiftCodes |
| Alliance management | Manage Alliance core/settings and tenant lifecycle. | Alliance |
| Membership and leadership | Membership, invitations, R1–R5 leadership and specialist roles. | Alliance |
| Recruitment | Intake/review/convert recruitment candidates through controlled membership handoff. | Alliance |
| Alliance content | Publish/manage Alliance-facing content and media. | Alliance |
| Kingdom governance | Manage Kingdom role/governance facts for Players. | GameWorld/Governance; workflows coordinate cross-context effects |
| Events | Define/schedule recurring Events and occurrences. | Operations/Events |
| Participation | Registration, responses and attendance. | Operations/Participation |
| Event planning | Rosters, polls, battle objectives and assignments. | Operations |
| Rallies | Plan and coordinate rallies against Event occurrences. | Operations/Rallies |
| King Perks | Plan/schedule King Perk appointments and King Skills with occupancy/cooldown rules. | Operations/KingPerks |
| Results | Capture operational Event results and metrics. | Operations/Results |
| Intelligence | Ingest observations and maintain roster/contribution/event/diplomacy intelligence. | Intelligence |
| Shared intelligence | Control sharing/grants and compose Kingdom intelligence views. | Intelligence + ReadModels |
| Communications | Deliver reminders/notifications with preferences/retry/idempotency. | Communications |
| Platform administration | Cross-tenant admin, lifecycle/retention controls and Event-type administration. | Platform |
| Integrations | Scoped API credentials and signed/retryable webhooks. | Platform/Integrations |
| Dashboards/history | Compose cross-context user-facing views without changing source ownership. | ReadModels |

This catalogue should change when a real product outcome changes, not for internal class/file movement.

## Assurance contract

Every capability in the catalogue carries the same five-part release obligation; a row is not considered delivered without it.

| Obligation | Authoritative evidence |
| --- | --- |
| Owner | The architectural owner in this catalogue and the canonical [capability map](../architecture/capability-map.md). Owner contexts retain writes; cross-context pages use read models or workflows. |
| Permission model | Active-Player and concrete-resource authorization through owner policies and services, indexed by the [permission reference](../reference/permissions.md). Public/read-only exceptions are explicit contracts, never implicit fallbacks. |
| Tests | Owner behavior, authorization, idempotency, architecture, frontend and applicable visual coverage described by the [testing contract](../codebase/testing.md). |
| Observability | Audit records for material mutations, correlation-aware request/job logging, outbox and delivery state, and the operational signals defined by [observability](../operations/observability.md). |
| Recovery | User correction or cancellation where the domain permits it, bounded retry/replay for external effects, operator diagnostics, and the applicable [recovery runbooks](../operations/recovery/README.md). |

Capability-specific reference pages refine these obligations. They may strengthen authorization, diagnostics, or recovery rules but may not omit them.
