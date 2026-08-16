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

Alliance authority resolves from the active Player's membership/rank/specialist roles. Kingdom authority resolves from Player-scoped GameWorld governance assignments. Operations and Intelligence own their permission vocabulary rather than embedding it into Alliance or GameWorld roles or a global catalogue.

**Permission ownership includes semantic interpretation, not only enum location.** Alliance and GameWorld expose their own role/rank/membership/governance facts and context-local permissions. A downstream context may interpret those facts for its own capability, but the lower context must not contain downstream permission keys, `NamedPermission` surrogates, or generic permission-bearing APIs that decide another context's policy.

For transaction-time writes, a lower context may expose a locked/current scope acquisition primitive. The downstream context then applies its own authorization policy against that locked scope. This preserves transaction ordering and current-state authority without making the lower context an authorization service for unrelated capabilities.

Transaction-time mutation authority continues to lock and reload the relevant Player/scope before authorization. User authentication never becomes a game-domain permission grant.

## ORM boundary rule

Cross-context foreign keys/reference IDs are allowed. Cross-context aggregate navigation is not. `GameWorld::Player` must not expose relationships such as memberships, roles, Event registrations, contributions or Rally assignments; owning contexts query by `player_id` or through ReadModels.

## Test rewrite rule

V2 acceptance is defined by tests owned by the V2 bounded context, not by stale V1 test directories. Legacy tests are rewrite inputs only: behavior that still belongs in the product must be restated under the owning V2 context or ReadModel/Workflow phase. A stale V1 test may not force a compatibility shim, recreate a deleted domain boundary, or block a completed hard cut.

The authoritative test tree mirrors context ownership. For the P1-P4 foundation this means `tests/Feature/Accounts`, `tests/Feature/GameWorld`, `tests/Feature/Alliance`, `tests/Unit/Accounts`, `tests/Unit/GameWorld`, `tests/Unit/Alliance`, and explicit downstream authorization contracts under Operations/Intelligence. Legacy noun folders and `tests/Unit/Authorization` are not V2 acceptance boundaries.

## P1-P4 alignment audit

A post-P5 audit revalidated the first four phases against the same clean-cut rules used by later phases. The audit is complete and the permanent Architecture V2 verifier is green.

- **P1:** dependency direction remains executable; V2 contexts may not depend on `App\\Domain`, Shared may not import business contexts, and upward context dependencies remain forbidden.
- **P2:** User remains account/platform identity only; Player remains the exact game principal; GameWorld foundation models have no reverse feature-aggregate navigation into Alliance/Operations/Intelligence.
- **P3:** Alliance test ownership now mirrors the production context (`Alliance/Core`, `Membership`, `Recruitment`, `Content`); Alliance permissions and default-role grants are Alliance-local only.
- **P4:** Alliance and GameWorld role/rank identity are separated from downstream capability semantics. Operations owns Event interpretation of Alliance rank/Event Coordinator identity; Intelligence owns its own Alliance-rank interpretation; neither permission vocabulary is embedded back into Alliance.
- `AllianceMutationAuthority` authorizes only Alliance permissions and separately exposes locked active-scope acquisition for downstream contexts.
- Architecture tests forbid downstream permission keys/`NamedPermission` inside Alliance Access and forbid downstream contexts from bypassing their context-owned Alliance authorization/mutation policies.
- Valid P4 contracts were restated under `tests/Unit/Alliance`, `tests/Unit/GameWorld`, `tests/Feature/Operations`, and `tests/Feature/Intelligence`; superseded `tests/Unit/Authorization` acceptance tests were deleted.

## Documentation end state

The current one-code-domain = one documentation-domain = mandatory five-profile structure will be retired. Final living documentation will move to `docs/architecture`, `docs/contexts`, and focused `docs/capabilities` documents. Superseded living domain contracts are removed rather than kept in parallel.

## Rewrite phases

### ARCH-V2-P0 — Freeze and inventory
**Implementation status:** complete.

### ARCH-V2-P1 — New skeleton and architecture enforcement
**Implementation status:** complete and re-audited. Dependency direction and Shared/foundation boundaries remain executable and green.

### ARCH-V2-P2 — Accounts and GameWorld foundation
**Implementation status:** complete and re-audited. User/Player separation, exact Player authority and foundation ORM boundaries are verified under context-owned tests.

### ARCH-V2-P3 — Alliance context
**Implementation status:** complete and re-audited. Alliance/Membership/Recruitment/Content are consolidated under one context-owned test tree; Player-scoped Alliance authority is enforced and Alliance contains only Alliance-local permission semantics.

### ARCH-V2-P4 — Access rewrite
**Implementation status:** complete and re-audited. The global `PermissionKey` and `app/Domain/Authorization` are deleted, permission semantics are context-owned, and the Architecture V2 verifier is green for PostgreSQL, Pint, architecture contracts, Larastan and context-owned runtime behavior.

- Alliance and Kingdom authority remain Player-scoped and are never granted directly to User;
- Alliance owns Alliance permissions plus rank/specialist-role facts, not Operations/Intelligence permission meaning;
- GameWorld owns Player/Kingdom governance state and locking without depending on Operations;
- Operations owns `events.*` permissions and the interpretation of Alliance officer/Event Coordinator identity for Event capabilities;
- Intelligence owns its own permission vocabulary and the interpretation of Alliance rank for Intelligence capabilities;
- downstream mutation policies acquire locked current scope from lower contexts and then authorize in the owning context;
- no compatibility aliases, `NamedPermission` downstream surrogates, generic V1 Authorization bridge or legacy Authorization acceptance suite remains.

### ARCH-V2-P5 — Operations rewrite
**Implementation status:** complete. The Events, Rallies and KingPerks V1 runtime roots are deleted and Architecture V2 verification is green on rewritten Operations-owned contracts.

- EventCore owns Event type, scope, occurrence, phase, scheduling and capability registration;
- participation, polls, rosters, battle plans, results and operational metrics live under Operations capability modules;
- Rallies and KingPerks live under Operations without separate event/calendar frameworks;
- Player-scoped event authority and exact Alliance/Kingdom checks remain enforced;
- Kingdom role identity remains GameWorld-owned while Operations attaches its own permissions through explicit workflow composition;
- `tests/Feature/Operations` and `tests/Unit/Operations` are the P5 behavior acceptance boundary;
- stale `tests/Feature/Events` and legacy-named test suites are not compatibility contracts and will be rewritten or removed in the owning later phase;
- the dedicated King Perks verifier now runs rewritten Operations test paths and is green for PostgreSQL, Pint, Larastan, backend contracts and frontend contracts.

### ARCH-V2-P6 — Intelligence rewrite
**Implementation status:** in progress from the verified P5 boundary.

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
