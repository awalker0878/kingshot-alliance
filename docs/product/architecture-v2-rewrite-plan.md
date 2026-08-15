# Architecture V2 big-bang rewrite

**Status:** Active rewrite plan  
**Branch:** `architecture-v2`  
**Baseline:** `d78a3371a2267982e9cdc278cb592228cfbb6a6a`  
**Rewrite mode:** clean replacement, not compatibility migration

## Objective

Replace the current noun-per-domain modular-monolith structure with a smaller set of meaningful bounded contexts, capability modules inside those contexts, explicit cross-context workflows, and an explicit compositional read side.

The rewrite preserves validated business/security invariants, especially Player-scoped game authority and transaction-time authorization, but does **not** preserve the current application architecture merely for compatibility.

## Non-negotiable rewrite rules

This repository is treated as a new application. There is no legacy consumer or production-data compatibility requirement for Architecture V2.

The rewrite therefore MUST NOT introduce:

- namespace aliases or `class_alias` bridges from `App\\Domain\\*` to V2 classes;
- `Legacy`, `Compatibility`, `Compat`, or transitional proxy layers used to preserve old application APIs;
- dual reads, dual writes, shadow tables, compatibility columns, transitional foreign keys, or backfill-only schema paths;
- old permission keys retained as aliases for new permissions;
- old routes retained only as redirects/aliases to preserve the previous internal route structure;
- old models wrapping new models or new models wrapping old persistence;
- test-only production helpers that preserve superseded APIs;
- deprecated services kept alive solely to make old call sites continue working; or
- documentation that describes both architectures as concurrently supported.

When a V2 capability replaces an old capability, all call sites and tests are rewritten to the V2 contract and the superseded implementation is deleted.

Because the application uses a fresh database model, V2 may replace/squash/reorder application migrations into a clean schema. Historical migration compatibility is not a product requirement.

## Security invariants that survive the rewrite

Architecture simplification must not weaken authority semantics:

- `User` is account/platform identity only.
- A User may own many Players.
- The active `Player` is the game-domain principal.
- Alliance/Kingdom/game-operation authority is Player-scoped.
- Platform Administrator is not a game-domain bypass.
- mutations authorize inside the transaction against the current locked authority state;
- aggregate/state locks remain domain-appropriate rather than globally standardized;
- historical facts remain keyed to durable Player/Alliance/Kingdom identity and are not rewritten when current placement changes; and
- cross-context orchestration does not create duplicate writable truth.

## Target application structure

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

### Accounts

Own global account identity/security only: User, authentication, verified email, profile, sessions, password, MFA and recovery.

### GameWorld

Own small neutral KingShot identity/governance foundations: Player, Kingdom, game-Alliance reference, active Player context, current game placement, Kingdom governance and Player transfer primitives.

Foundation models expose identity/reference state only. They do not expose ORM navigation into higher-level contexts.

### Alliance

Own platform Alliance lifecycle and cohesive Alliance operations: Alliance core/settings, membership, R1-R5 leadership, specialist roles, invitations, recruitment and Alliance content.

### Operations

Own operational event execution as capability modules rather than peer top-level domains:

- EventCore
- Participation
- Polls
- Rosters
- BattlePlans
- Rallies
- KingPerks
- Results
- Metrics

`EventCore` owns identity/scope/schedule/occurrence/phase/capability selection; capability modules own their own state and behavior.

### Intelligence

Own observation, ingestion, analytical and reporting concerns:

- player/Alliance/roster observations and snapshots;
- ingestion/reconciliation/quarantine/CSV intake;
- contribution ledger/corrections;
- reporting/exports/leaderboards/trends;
- intelligence sharing/grants/consent/history; and
- diplomacy/intelligence analysis.

Neutral GameWorld identity is not observation/intelligence state.

### Communications

Own notification/reminder delivery, preferences, retry/idempotency and channel behavior. Source contexts own the fact that caused a notification; Communications owns delivery coordination.

### Platform

Own actual cross-tenant SaaS/platform administration: administrator grants, lifecycle controls, entitlements, feature flags, legal hold, retention/account orchestration, usage and external integrations.

Generic transactional messaging/outbox and audit infrastructure move out of the Platform business context into Shared infrastructure.

### Shared

Contains only genuinely cross-cutting technical contracts/infrastructure such as access primitives, audit recording, messaging/outbox, clock/time, IDs and transaction helpers. Shared contains no feature business aggregate.

### Workflows

Own explicit cross-context business orchestration such as Kingdom transfer, Alliance leadership transfer, onboarding and account deletion. A workflow coordinates context public APIs without taking persistence ownership from them.

### ReadModels

May intentionally compose/join data across contexts for dashboards, history and reporting. ReadModels are read-only and may never mutate source state.

## Dependency direction

```text
Accounts
   |
   v
GameWorld
 /      \
v        v
Alliance  Operations
  \       /
   \     /
 Intelligence
      |
      v
Communications

Shared <---- all contexts
Platform ---- orchestrates context public APIs
ReadModels -- read any context, never write
```

Required directional rule: `GameWorld` must never depend on Alliance, Operations or Intelligence feature models.

## Authorization V2

Delete the single global business-permission enum as the extension point. Permission definitions live with the owning context/capability and implement a small shared permission contract/registry.

One scope-aware access engine resolves current Player authority for a concrete resource scope. Capability-specific policy remains with the owning context.

V2 replaces proliferating mutation-authority variants with shared transaction-time access primitives plus context-specific policy/locking, without weakening lock-before-authorize requirements.

## ORM boundary rule

Cross-context foreign keys/reference IDs are allowed. Cross-context aggregate navigation is not.

For example, `GameWorld::Player` must not expose relationships such as memberships, roles, Event registrations, contributions or Rally assignments. Those contexts query their own state by `player_id` or through ReadModels.

## Documentation end state

The current one-code-domain = one documentation-domain = mandatory five-profile structure will be retired.

After runtime replacement is complete, living documentation will be reorganized approximately as:

```text
docs/
├── architecture/
│   ├── contexts.md
│   ├── dependency-rules.md
│   ├── security-model.md
│   └── mutation-model.md
├── contexts/
│   ├── accounts.md
│   ├── game-world.md
│   ├── alliance.md
│   ├── operations.md
│   ├── intelligence.md
│   ├── communications.md
│   └── platform.md
└── capabilities/
    └── focused documents only where lifecycle/operational complexity warrants them
```

Historical acceptance/security evidence may remain in an archive/evidence location when it is still useful, but superseded living domain contracts and old architecture maps are removed rather than kept as parallel guidance.

## Rewrite phases

### ARCH-V2-P0 — Freeze and inventory

- pin the V1 baseline;
- inventory current domains, schema families, routes, jobs, UI/test surfaces and architectural constraints;
- record old→new ownership mapping;
- freeze the no-compatibility contract.

### ARCH-V2-P1 — New skeleton and architecture enforcement

- create `Contexts`, `Shared`, `Workflows`, and `ReadModels` roots;
- create dependency/layer architecture tests;
- establish V2 namespace/module conventions;
- forbid new V1 domain additions during rewrite.

### ARCH-V2-P2 — Accounts and GameWorld foundation

- move User/account security to Accounts;
- rebuild Player/Kingdom/game-Alliance identity in GameWorld;
- remove cross-context ORM navigation from foundation models;
- rebuild active Player context.
- keep invitation onboarding out of Accounts; the Alliance onboarding workflow is rebuilt in P3.
- defer cross-context account deletion orchestration to Workflows in P8 rather than importing V1 dependencies.

### ARCH-V2-P3 — Alliance context

- merge Alliance core, membership, leadership/R1-R5, specialist roles, invitations, recruitment and content into cohesive Alliance capability modules;
- rewrite all tests/call sites directly to V2 contracts.

### ARCH-V2-P4 — Access rewrite

- replace global `PermissionKey` and specialized mutation-authority proliferation;
- introduce context-owned permissions/policies plus scope-aware access engine;
- preserve transaction/lock-time authority invariants.

### ARCH-V2-P5 — Operations rewrite

- rebuild EventCore and move participation, polls, rosters, battle plans, Rallies, KingPerks, results and metrics under Operations;
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
- move cross-context dashboards/history/reporting composition to ReadModels;
- remove feature-domain query classes that exist only because reads were forced into write owners.

### ARCH-V2-P9 — Clean schema and route/bootstrap cutover

- replace old application migrations with one clean fresh-database schema sequence;
- rename tables/constraints where V1 ownership names are misleading;
- delete obsolete pivots/FKs/columns;
- rebuild route/bootstrap/provider registration around V2 modules;
- no schema compatibility/backfill path.

### ARCH-V2-P10 — Delete V1 and rebuild living documentation

- delete `app/Domain` after all behavior is represented in V2;
- delete obsolete route files/providers/imports/tests;
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