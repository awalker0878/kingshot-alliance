# Capability catalogue

Status: Current

This is the user/product view of implemented and actively delivered capability groups. Architectural ownership is linked where useful.

| Product capability | Outcome | Architectural owner |
| --- | --- | --- |
| Account security | Register, authenticate, verify email, manage profile/password/MFA/recovery. | Accounts |
| Player context | Own/claim Players and operate as one active game persona at a time. | GameWorld/Players; workflows coordinate cross-context effects |
| Gift Codes | Preserve source history and explicit trust, prepare official redemption for current/all/failed Governors, track per-Governor outcomes, and warn before expiry. | GameWorld/GiftCodes |
| Alliance management | Manage Alliance core/settings and tenant lifecycle. | Alliance |
| Membership and leadership | Membership, invitations, R1–R5 leadership and specialist roles. | Alliance |
| Recruitment | Intake, filter, preview/bulk-triage, review and convert recruitment candidates through controlled membership handoff. | Alliance |
| Alliance content | Publish reviewed, revisioned and context-linked knowledge plus testable timezone-safe recurring announcements with delivery history and selective recovery. | Alliance intent + Communications delivery + ReadModels composition |
| Kingdom governance | Manage Kingdom role/governance facts for Players. | GameWorld/Governance; workflows coordinate cross-context effects |
| Territory & hive planning | Build, validate, analyze, version, compare and share Alliance/Kingdom layouts using versioned KingShot map facts; plan HQs, Banners, Governor cities, Bear Traps, territory coverage, hive presets, march times and multi-Alliance positioning. | GameWorld/KingdomMaps owns map facts/rules; Operations/TerritoryPlanning owns plans/analysis; ReadModels composes editor reads |
| Events | Define/schedule recurring Events and occurrences. | Operations/Events |
| Participation | Registration, responses and attendance. | Operations/Participation |
| Event planning | Rosters, polls, battle objectives and assignments. | Operations |
| Rallies | Plan and coordinate rallies against Event occurrences. | Operations/Rallies |
| King Perks | Plan/schedule King Perk appointments and King Skills with occupancy/cooldown rules. | Operations/KingPerks |
| Results | Capture operational Event results and metrics. | Operations/Results |
| Intelligence | Ingest observations and maintain roster/contribution/event/diplomacy intelligence. | Intelligence |
| Shared intelligence | Control sharing/grants and compose Kingdom intelligence views. | Intelligence + ReadModels |
| Communications | Deliver reminders/notifications with preferences/retry/idempotency. | Communications |
| Platform administration | Cross-tenant admin, lifecycle/retention controls, Event-type administration, privacy-safe diagnostics and audited outbox recovery. | Platform + ReadModels composition |
| Integrations | Scoped API credentials, revocable external-actor pairing, idempotent Event participation adapters, and signed/retryable webhooks. | Platform/Integrations |
| Dashboards/history | Compose cross-context user-facing views without changing source ownership. | ReadModels |

This catalogue should change when a real product outcome changes, not for internal class/file movement.

## Territory & Hive Planner product contract

The capability is delivered as one complete product, not as disconnected map widgets. The final user outcome includes all of the following:

- versioned KingShot map datasets with provenance, observation date, schema version and checksum;
- one canonical coordinate/geometry vocabulary used by server validation and browser preview;
- saved Alliance and Kingdom-scoped plans with optimistic revision protection;
- planned HQs, Banners, Governor cities, Bear Traps and plan-local external Alliance/Governor references;
- server-authoritative collision, exclusion-zone, map-boundary, footprint, cap and territory-connectivity validation;
- explicit separation between invalid game-rule violations, planning warnings and optimization suggestions;
- pan/zoom, select, move, delete, duplicate, box-select, grouping, ungrouping, rotation, keyboard movement and undo/redo;
- territory coverage rendering and analysis;
- Bear-hive presets/generators and editable generated layouts;
- march-distance/time analysis with visible, versioned assumptions where no authoritative game value exists;
- multi-Alliance Kingdom planning without requiring external Alliances or Governors to become application records;
- immutable published revisions, comparison, clone/restore, schema-versioned JSON import/export, PNG/SVG image export and shareable artifacts;
- keyboard/mobile/accessibility parity through synchronized DOM controls rather than a canvas-only workflow;
- integration with applicable Bear Hunt, Castle Battle and Kingdom of Power/Kingdom planning workflows through immutable plan-revision references rather than moving spatial state into BattlePlans.

Community projects are discovery evidence, not authoritative KingShot truth. A community-derived coordinate, footprint, placement rule or march constant cannot silently become product logic; it must carry the provenance/confidence contract defined by GameWorld/KingdomMaps.

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
