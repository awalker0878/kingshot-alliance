# Kingdoms domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current — `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` Accepted  
**Code owner:** `app/Domain/Kingdoms`  
**Primary authorization boundary:** `alliance.view` for member-safe reads; `kingdoms.manage` for management/private workflows; `alliance.manage` for Alliance→Kingdom setting

## 1. Purpose and ownership

Kingdoms owns approved Kingshot game-world reference identity and Alliance-owned Kingdoms workflows:

- global `Kingdom` reference identity;
- global neutral `KingdomPlayer` identity scoped to a Kingdom;
- global neutral `KingdomAlliance` identity scoped to a Kingdom;
- Alliance-owned roster state and append-only player observations;
- descriptive roster intelligence;
- controlled CSV roster migration/export;
- Alliance-owned transfer planning and explicit roster handoff; and
- Alliance-owned game-side Alliance tracking, factual observations, explicit diplomacy, manager-private contacts, and descriptive Alliance intelligence.

Accepted increments:

- [`KINGDOMS-001` — roster intelligence](../../product/kingdoms-roster-intelligence-increment.md);
- [`KINGDOMS-002` — transfer planning](../../product/kingdoms-transfer-planning-increment.md); and
- [`KINGDOMS-003` — Alliance intelligence and diplomacy](../../product/kingdoms-alliance-intelligence-increment.md).

## 2. Scope

### In scope

- neutral Kingdom/player/game-Alliance reference identity;
- Alliance→Kingdom association consumption;
- tenant-owned roster entries and player snapshots;
- controlled CSV roster migration/export;
- descriptive roster metrics/trends/data quality;
- transfer plans/participants/groups/readiness/blockers/completion;
- neutral game-Alliance tracking;
- append-oriented game-Alliance observations/corrections;
- explicit manager-maintained diplomacy/NAP state/history;
- manager-private diplomacy contacts; and
- descriptive game-Alliance intelligence/trends/review indicators.

### Out of scope

- Alliance/application identity and membership ownership;
- automated game-data ingestion/scraping/OCR/bots;
- cross-Alliance/shared Kingdom intelligence without a separately approved opt-in scope;
- threat/desirability/punitive/player scoring or automatic recommendations;
- automated diplomacy/negotiation/transfer execution;
- transfer resource/pass/ticket optimization; and
- public Kingdoms API/webhook contracts.

## 3. Domain model

### Identity layers

Kingdoms deliberately separates:

1. **Application identity** — global `User`, owned by Identity.
2. **Alliance membership** — User↔Alliance relationship, owned by Memberships.
3. **Game player identity** — neutral `KingdomPlayer` within a `Kingdom`, owned by Kingdoms.
4. **Game Alliance identity** — neutral `KingdomAlliance` within a `Kingdom`, owned by Kingdoms.
5. **Alliance-owned observations/workflows** — roster, snapshots, imports/intelligence, transfer planning, game-Alliance tracking/observations/diplomacy/contacts, owned by Kingdoms beneath explicit Alliance tenancy.

### Kingdom

A `Kingdom` is global reference data with canonical positive Kingdom number, lifecycle state, and timestamps. An Alliance stores `kingdom_id`; the legacy free-form Alliance Kingdom persistence column is removed.

### KingdomPlayer

A `KingdomPlayer` is neutral reference identity scoped to one Kingdom. Stable game-player ID inside that Kingdom is the only automatic player identity-match key. Display names are not unique and never auto-merge identity.

### KingdomAlliance

A `KingdomAlliance` is neutral game-side Alliance identity scoped to one Kingdom. Stable `game_alliance_id` inside that Kingdom is the only automatic game-Alliance identity key. Name, tag, contact handle, or diplomacy state never auto-merge identity.

### Tenant-owned roster/history

`AllianceRosterEntry` belongs to one platform Alliance and one neutral `KingdomPlayer`. `PlayerSnapshot` is append-only Alliance-owned historical observation.

### Transfer planning

Transfer state is Alliance-owned and includes plan, participant, group, readiness transition, blocker, and completion/handoff records.

### Alliance intelligence/diplomacy

`TrackedKingdomAlliance` is an Alliance-owned relationship to a neutral `KingdomAlliance`. Observations, diplomacy/current+transition history, contacts, private notes, and derived intelligence remain tenant-owned.

## 4. Core invariants

1. Global neutral references never grant tenant access.
2. Stable game identifiers within one Kingdom are the only automatic neutral identity keys.
3. Display names/tags/handles never auto-merge neutral identity.
4. Alliance-owned Kingdoms reads/mutations begin from explicit active Alliance context.
5. Submitted tenant-owned IDs are re-resolved beneath the active Alliance/plan/tracking boundary.
6. Player and game-Alliance history is append-oriented; accepted corrections preserve the original evidence rather than rewriting it.
7. Missing data remains distinct from recorded zero.
8. Descriptive intelligence derives from accepted facts/history and does not persist hidden ranking scores.
9. Diplomacy changes only through explicit human manager action; review/expiry timestamps never auto-transition state.
10. Transfer completion is explicit/idempotent and never fabricates a player snapshot.
11. Coordinator/contact responsibility never grants authorization.
12. Internal `kingdoms.*` outbox events do not automatically become public webhooks.

## 5. Lifecycles and workflows

### Alliance Kingdom setting

Changing the Alliance's Kingdom remains an Alliances-owned setting under `alliance.manage`. Archived Kingdoms cannot be newly selected. Kingdoms workflows that capture Kingdom context fail closed after drift rather than silently retargeting historical/planning state.

### Roster and player observation

Managers resolve/create neutral player identity, maintain Alliance-owned roster relationship, optionally link an active same-Alliance membership, mark roster state, and record append-only player observations.

See [Roster](roster.md) and [Snapshots](snapshots.md).

### Roster intelligence

Current/stale/missing quality, exact recorded-power aggregates, linkage coverage, movement, and bounded 7/30-day trends are derived synchronously from accepted roster/snapshot history.

See [Roster intelligence](intelligence.md).

### Controlled CSV migration

A strict `kingdoms-roster.v1` dry-run/confirm workflow performs bounded validation, stable-ID-only automatic matching, explicit name-ambiguity resolution, transactional confirmation, provenance, drift detection, idempotency, and safe export.

See [CSV migration](csv-migration.md).

### Transfer planning

Accepted planning supports draft/open/locked/closed/cancelled cycles, incoming/outgoing/staying intent, groups/coordinators, manual readiness/blockers, and explicit per-participant completion/roster handoff.

See [Transfer planning](transfer-planning.md).

### Game-Alliance tracking and intelligence

Managers track neutral game-side Alliances in current Kingdom context, append factual observations/corrections, explicitly maintain diplomacy/NAP state/history, keep minimal manager-private handle-based contacts, and view read-only descriptive intelligence/trends.

See [Alliance intelligence and diplomacy](alliance-intelligence.md).

## 6. Authorization and tenancy

- member-safe Kingdoms reads use `alliance.view`;
- roster/snapshot/import/transfer/game-Alliance/diplomacy/contact management uses `kingdoms.manage`;
- privileged mutations require recent password confirmation; and
- Alliance→Kingdom setting uses `alliance.manage`.

Global `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` rows are reference identity, not authorization boundaries.

Ordinary member payloads exclude manager notes, membership emails/management IDs, restricted blocker detail, snapshot actor/import-management metadata, diplomacy private terms/rationale, contact detail, and richer completion provenance.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active tenant context and canonical Alliance→Kingdom relation.
- **Memberships** — optional same-Alliance roster linkage/coordinator references; membership identity never becomes game identity.
- **Authorization** — `alliance.view`, `alliance.manage`, `kingdoms.manage`.
- **Identity** — actor identity/recent password assurance.
- **Audit/Platform** — audit and transactional-outbox infrastructure.
- **Integrations** — explicit external-exposure boundary that currently excludes Kingdoms public contracts.

### Exposes

- member-safe roster/history/intelligence/transfer/diplomacy presentation;
- manager-only accepted mutation/query contracts; and
- internal durable `kingdoms.*` events for asynchronous evidence/coordination, not public webhook compatibility.

## 8. Persistence and data ownership

Neutral Kingdom/player/game-Alliance references are global reference data. Roster entries, snapshots, imports, transfer plans and related records, tracking, observations, diplomacy, contacts, corrections, and derived tenant intelligence are Alliance-owned.

The global-reference/tenant-observation split is mandatory: sharing a neutral reference never exposes another tenant's notes, history, metrics, transfer state, diplomacy, contacts, or derived summaries.

## 9. Events, outbox and integrations

Material privileged Kingdoms mutations create audit and transactional-outbox evidence.

`alliance.kingdom_updated` and all `kingdoms.*` event families remain internal and are excluded from generic external webhook fan-out, including wildcard subscriptions. Public Kingdoms API/webhook exposure requires a separately approved integration contract with documented schemas/tests.

## 10. HTTP, UI and API surfaces

Current first-party surfaces include roster/history/intelligence/import/export, transfer planning/management/completion, tracked game-Alliance observation/diplomacy/contact workspaces, and descriptive Alliance-intelligence dashboard.

The existing read-only public API has no accepted Kingdoms roster/snapshot/intelligence/transfer/diplomacy route/scope.

## 11. Background processing

Accepted K1–K3 Kingdoms behavior is primarily synchronous request/query behavior using PostgreSQL, audit, and the shared outbox publisher.

It adds no Kingdoms-specific crawler, scraper, OCR pipeline, game-data ingestion worker, autonomous transfer executor, or diplomacy automation.

## 12. Failure, idempotency and concurrency

- exact player/game-Alliance observation retries resolve deterministically instead of multiplying history;
- CSV committed-import identity prevents duplicate batch application;
- transfer completion has one durable completion per participant and returns the existing result on retry before repeating delegated roster side effects;
- Alliance-Kingdom drift preserves authorized historical reads but normal mutations fail closed;
- ambiguous name/tag/handle identity is never automatically resolved; and
- trend calculations return missing/insufficient history rather than fabricate/interpolate unsupported values.

## 13. Security and privacy

Kingdoms contains high-value Alliance operational intelligence. Manager-private notes, blocker detail, diplomacy terms/rationale, contacts, actor provenance, and import-management data must not leak into ordinary member, other-tenant, audit/outbox, or public integration payloads beyond explicitly approved safe identifiers.

See the accepted K1/K2/K3 security reviews under `docs/security/`.

## 14. Observability and operations

Accepted operations guidance covers roster intelligence, transfer planning, and Alliance intelligence. Historical/provenance fields and data-quality indicators are designed to distinguish missing/stale/invalidated facts from zero/current values.

- [Roster intelligence operations](../../operations/kingdoms-roster-intelligence.md)
- [Transfer planning operations](../../operations/kingdoms-transfer-planning.md)
- [Alliance intelligence operations](../../operations/kingdoms-alliance-intelligence.md)

## 15. Testing and architecture enforcement

The Kingdoms suites protect:

- neutral reference versus tenant-owned state;
- stable-ID identity matching/no name auto-merge;
- authorization/tenant isolation;
- append-only observation/correction history;
- exact/idempotent retries;
- CSV drift/ambiguity/formula safety;
- transfer lifecycle/readiness/blocker/completion invariants;
- diplomacy/contact privacy and human-only state transitions;
- bounded 7/30-day trend semantics;
- query-count gates at realistic volumes; and
- external API/webhook non-exposure.

Architecture tests additionally protect the Kingdoms physical/module boundaries.

## 16. Explicit non-capabilities

The accepted runtime does not implement:

- scraping, OCR, bots, or undocumented/unapproved Kingshot APIs;
- automated game-data ingestion;
- cross-Alliance/shared Kingdom intelligence;
- transfer marketplace/public advertising;
- inferred transfer eligibility/readiness;
- transfer pass/ticket/resource optimization;
- player/Alliance/destination threat/desirability/punitive ranking;
- automated stay/leave/diplomacy/attack recommendations;
- automatic negotiation/diplomacy transitions;
- bulk/automatic in-game transfer execution; or
- public Kingdoms API/webhook contracts.

## 17. Capability documents

- [Roster](roster.md)
- [Player snapshots](snapshots.md)
- [Roster intelligence](intelligence.md)
- [Controlled CSV migration](csv-migration.md)
- [Transfer planning](transfer-planning.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)

## 18. Related documentation

- [KINGDOMS-001 exit report](../../product/kingdoms-roster-intelligence-exit-report.md)
- [KINGDOMS-002 exit report](../../product/kingdoms-transfer-planning-exit-report.md)
- [KINGDOMS-003 exit report](../../product/kingdoms-alliance-intelligence-exit-report.md)
- [Alliances domain](../alliances/README.md)
- [Memberships domain](../memberships/README.md)
- [Authorization domain](../authorization/README.md)
- [Integrations domain](../integrations/README.md)
- [`app/Domain/Kingdoms/README.md`](../../../app/Domain/Kingdoms/README.md)
