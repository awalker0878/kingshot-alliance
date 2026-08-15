# Architecture V2 — Big-Bang Rewrite Plan

## Purpose

Replace the current noun-per-domain architecture with a smaller set of cohesive bounded contexts that own related capabilities and business invariants.

This is a **big-bang replacement**, not an incremental compatibility migration. V2 code replaces V1 code directly. There are no dual APIs, compatibility facades, aliases, shadow writes, legacy fallbacks, or deprecation periods inside the rewrite branch.

## Security model

The application has two deliberately separate authority layers:

- `User` is the account/platform identity. It owns authentication, account security, email delivery identity and true platform-administrator authority only.
- `Player` is the game-domain principal. Alliance membership, R1-R5 rank, specialist Alliance roles, Kingdom roles, visibility and all game-domain permissions are Player-scoped.

A User may own many Players. Game authority is never aggregated across those Players. The selected active Player determines the effective game-domain identity and therefore the Alliance and Kingdom authority visible to the request. Platform Administrator is not a game-domain authorization bypass.

## Architectural goals

- model cohesive business capabilities rather than creating a domain for every noun;
- make ownership and transaction boundaries obvious;
- preserve Player-scoped game authority and exact tenant/Kingdom isolation;
- remove cross-context ORM navigation from foundation models;
- allow read composition without giving read models mutation authority;
- remove the global business permission catalogue as an extension point;
- make dependencies flow in one intentional direction;
- keep Shared limited to technical contracts/infrastructure;
- eliminate all superseded V1 runtime, tests and living documentation by the end of the rewrite.

## Target application roots

```text
app/
├── Contexts/
│   ├── Accounts/
│   ├── GameWorld/
│   ├── Alliance/
│   ├── Operations/
│   ├── Intelligence/
│   ├── Communications/
│   └── Platform/
├── Shared/
├── Workflows/
└── ReadModels/
```

## Context responsibilities

### Accounts
Own account authentication/security/profile behavior and the User aggregate. Account identity does not grant game-domain authority.

### GameWorld
Own neutral KingShot identity and placement facts: Player, Kingdom, game Alliance identity/observation primitives and Kingdom governance state that is not specific to a downstream feature.

### Alliance
Own Alliance lifecycle, membership, R1-R5 leadership, specialist roles, invitations, recruitment and Alliance content. Alliance authority is resolved from the active Player's current Alliance membership, rank and specialist roles. A User never directly holds Alliance membership, role or permission grants.

### Operations
Own planned and live Alliance/Kingdom operations: Event core/scheduling, participation, polls, rosters, battle plans, Rallies, King Perks, results and operational metrics.

### Intelligence
Own observed/imported game state and analysis: roster observations, Kingdom observations, ingestion, sharing policy, diplomacy, Contributions and intelligence/reporting projections.

### Communications
Own notification/reminder delivery, preferences, retry/idempotency and channel behavior. Source contexts own the fact that caused a notification; Communications owns delivery coordination.

### Platform
Own actual cross-tenant SaaS/platform administration: administrator grants, lifecycle controls, entitlements, feature flags, legal hold, retention/account orchestration, usage and external integrations.

### Shared
Contains only genuinely cross-cutting technical contracts/infrastructure such as access primitives, audit recording, messaging/outbox, clock/time, IDs and transaction helpers. Shared contains no feature business aggregate.

### Workflows
Own explicit cross-context business orchestration such as Kingdom transfer, Alliance leadership transfer, onboarding and account deletion. A workflow coordinates context public APIs without taking persistence ownership from them.

### ReadModels
May intentionally compose/join data across contexts for dashboards, history and reporting. ReadModels are read-only and may never mutate source state.

## Authorization V2

The global business-permission enum has been deleted. Permission definitions live with their owning contexts/capabilities and implement the Shared `Permission` contract.

Alliance authority resolves from the active Player's membership/rank/specialist roles. Kingdom authority resolves from Player-scoped GameWorld governance assignments. Operations and Intelligence own their permission vocabulary rather than embedding it into GameWorld roles or a global catalogue.

Transaction-time mutation authority continues to lock and reload the relevant Player/scope before authorization. User authentication never becomes a game-domain permission grant.

## ORM boundary rule

Cross-context foreign keys/reference IDs are allowed. Cross-context aggregate navigation is not. `GameWorld::Player` must not expose relationships such as memberships, roles, Event registrations, contributions or Rally assignments; owning contexts query by `player_id` or through ReadModels.

## Documentation end state

The current one-code-domain = one documentation-domain = mandatory five-profile structure will be retired. Final living documentation will move to `docs/architecture`, `docs/contexts`, and focused `docs/capabilities` documents. Superseded living domain contracts are removed rather than kept in parallel.

## Rewrite phases

### ARCH-V2-P0 — Freeze and inventory
**Implementation status:** complete.

### ARCH-V2-P1 — New skeleton and architecture enforcement
**Implementation status:** complete.

### ARCH-V2-P2 — Accounts and GameWorld foundation
**Implementation status:** complete.

### ARCH-V2-P3 — Alliance context
**Implementation status:** complete. Architecture V2 verification is green for schema, Pint, architecture contracts, Larastan, Accounts/GameWorld behavior and Alliance bounded-context behavior.

### ARCH-V2-P4 — Access rewrite
**Implementation status:** complete. The global `PermissionKey` and `app/Domain/Authorization` have been deleted. Architecture V2 verification is green for PostgreSQL, Pint, Player-scoped authority contracts, Larastan and Accounts/GameWorld/Alliance runtime behavior.

- context-owned permission enums define Alliance, Kingdom, Operations and Intelligence access vocabulary;
- Alliance and Kingdom authority remain Player-scoped and are never granted directly to User;
- GameWorld owns Player/Kingdom governance state and locking without depending on Operations;
- Operations owns `events.*` permissions and attaches them to Kingdom roles through an explicit cross-context bootstrap workflow;
- no compatibility aliases or V1 Authorization bridge remain.

### ARCH-V2-P5 — Operations rewrite
**Implementation status:** in progress from the verified P4 boundary.

- rebuild EventCore around Event type, scope, occurrence, phase, schedule and capability registration;
- move participation, polls, rosters, battle plans, results and operational metrics into Operations capability modules;
- move Rallies and KingPerks into Operations without creating separate event/calendar frameworks;
- preserve Player-scoped event authority and exact Alliance/Kingdom scope checks;
- delete replaced Events/Rallies/KingPerks code rather than bridge it.

### ARCH-V2-P6 — Intelligence rewrite
- split observations/ingestion/sharing/diplomacy from old Kingdoms;
- move Contributions/reporting/history/analytics to Intelligence/ReadModels;
- keep Event facts owned by Operations.

### ARCH-V2-P7 — Communications, Platform and shared infrastructure
- generalize Notifications into Communications;
- keep Platform focused on product/platform administration;
- move Audit/outbox/messaging infrastructure into Shared;
- relocate Integrations under Platform while preserving explicit producer ownership.

### ARCH-V2-P8 — Workflows and ReadModels
- move genuine cross-context orchestration to Workflows;
- move cross-context dashboards/history/reporting composition to ReadModels.

### ARCH-V2-P9 — Clean schema and route/bootstrap cutover
- replace old application migrations with one clean fresh-database schema sequence;
- rebuild route/bootstrap/provider registration around V2 modules;
- no schema compatibility/backfill path.

### ARCH-V2-P10 — Delete V1 and rebuild living documentation
- delete `app/Domain` after all behavior is represented in V2;
- remove old architecture/domain documentation and mandatory profile structure;
- create final `docs/architecture`, `docs/contexts`, and focused capability docs;
- update README/contributor guidance.

### ARCH-V2-P11 — Whole-application verification
- fresh PostgreSQL bootstrap;
- complete backend test suite;
- Larastan/Pint;
- frontend ESLint/Prettier/Vue TypeScript/build;
- authorization/isolation/race tests;
- route and scheduler verification;
- visual/mobile/accessibility checks;
- deployment verification against the clean database.

## Completion rule

Architecture V2 is complete only when the repository has one active application architecture. `App\\Domain\\*`, its living-domain documentation structure, old compatibility concepts and superseded tests must be absent from the final runtime branch.
