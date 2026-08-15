# EVENT-CONTRIB-001 — Event contribution history implementation plan

[← Events domain](../README.md)

**Document type:** Implementation plan  
**Status:** Active  
**Owning domains:** Events and Contributions  
**Architecture authority:** [ADR 0011](../../../adr/0011-event-history-and-contribution-ownership.md)

## Objective

Deliver one durable historical model across Player-, Alliance-, and Kingdom-scoped KingShot Events so:

- a Player can view that Player's lifetime contribution/Event history across Alliance and Kingdom changes;
- current Alliance leadership can view the Alliance's complete historical Event record, including former members;
- current Kingdom leadership can view the Kingdom's complete historical Event record, including Players who later transfer; and
- Event metrics remain explainable and comparable only where their definitions are compatible.

The database is greenfield for this program. Canonical migrations/schema are changed directly to the final model; no compatibility columns, dual-write shims, backfills, or legacy User/membership authority paths are introduced.

## Canonical identity hierarchy

```text
Kingdom
└── id

Alliance
├── id            canonical Alliance identity
├── kingdom_id    → Kingdom.id
├── name
└── lifecycle/settings

Player
├── id            durable Player identity
└── current_kingdom_id → Kingdom.id
```

Every Event, result, membership, roster and historical context row uses `alliance_id` to reference `alliances.id`. `kingdom_id` belongs on Alliance and identifies the Kingdom that Alliance belongs to. Event history does not introduce a second GameAlliance/KingdomAlliance identity.

Alliance names are presentation data and are not identity. The same name may exist in different Kingdoms.

## Phase status

| Phase | Scope | Status |
| --- | --- | --- |
| EC-P0 | Final ownership, authorization, history and metric semantics | **Complete** |
| EC-P1 | Greenfield schema redesign and immutable historical targets | **Complete** |
| EC-P2 | KingShot Event metric catalogue | **Complete** |
| EC-P3 | Result/metric capture and derived participation facts | **Next** |
| EC-P4 | Player cross-scope history query | Planned |
| EC-P5 | My Contributions / History UX | Planned |
| EC-P6 | Alliance historical Event intelligence | Planned |
| EC-P7 | Kingdom historical Event intelligence | Planned |
| EC-P8 | Contribution report/export integration | Planned |
| EC-P9 | Trend, leaderboard and comparative intelligence | Planned |
| EC-P10 | Security, isolation and concurrency tests | Planned |
| EC-P11 | Query/index/performance review | Planned |
| EC-P12 | Documentation and final architecture cleanup | Planned |

## EC-P0 — Final domain contract — COMPLETE

### Decisions

1. Player history follows durable `player_id`.
2. Alliance Event history follows immutable Event `alliance_id`.
3. Kingdom Event history follows immutable Event `kingdom_id`.
4. Current authority controls organization-wide read access; current membership/current Kingdom placement never determines historical ownership.
5. Events owns Event facts and metric persistence.
6. Contributions owns unified history/report composition and its own non-Event contribution ledger.
7. Historical Player/Alliance/Kingdom context snapshots are evidence only and never authorization state.
8. Platform Administrator status grants no game-domain Event-history bypass.
9. Incompatible Event metrics are not combined into an unexplained universal score.
10. Greenfield implementation replaces incompatible schema/code directly rather than carrying compatibility assumptions.

### Exit evidence

- ADR 0011 accepted and indexed.
- Events living contract updated.
- Contributions living contract updated.
- Event results/intelligence contract updated.
- Event history composition contract no longer describes Event-to-Contribution materialization.
- Architecture regression test protects the P0 contract.

## EC-P1 — Greenfield schema redesign — COMPLETE

Canonical migrations were changed directly because the database is empty.

### Delivered schema

- `Alliance` remains the single canonical Alliance identity. `alliances.kingdom_id` is required and identifies the Kingdom the Alliance belongs to.
- `events.scope` plus the exact `player_id | alliance_id | kingdom_id` target are immutable after creation at the database boundary.
- Event target foreign keys use restrictive retention semantics rather than cascading away historical ownership.
- `events.target_display_name` and `events.target_secondary_label` preserve creation-time display evidence independently of current names.
- `event_occurrences.event_id` uses restrictive deletion semantics so result-bearing historical occurrences cannot be silently erased with their Event.
- `event_metric_definitions` defines stable metrics by Event Type scope and structural subject.
- Metric subjects are `event`, `alliance`, and `player`.
- `event_result_metrics`, `event_alliance_result_metrics`, and `event_player_result_metrics` store typed numeric values with definition, optional dimension, provenance source, recorder and timestamp.
- Legacy result-level JSON `metrics` columns and write parameters are removed rather than retained as compatibility fields.
- `event_alliance_results` records Alliance contribution inside Kingdom Events by canonical `alliance_id`, including frozen name/tag evidence.
- The database rejects an `event_alliance_results.alliance_id` whose `alliances.kingdom_id` does not match the Kingdom Event's `kingdom_id`.
- `event_player_contexts` stores durable Player identity plus occurrence-time name, `kingdom_id_at_event`, optional represented `alliance_id`, and frozen represented-Alliance display evidence.
- The database rejects represented Alliance context when that Alliance's `kingdom_id` differs from `kingdom_id_at_event`.
- Natural uniqueness is enforced for occurrence result, occurrence + Player, occurrence + Alliance, occurrence + Player context, and metric definition/dimension per result.
- History-oriented indexes cover Player history, represented Alliance, Kingdom-at-event, metric definition and occurrence-result access.

### Runtime/model alignment

- Event creation snapshots target display metadata from the current authoritative target inside the creation transaction.
- `EventResult` and `EventPlayerResult` expose normalized metric relations instead of JSON metrics.
- `EventOccurrence` exposes Event-wide, Alliance, Player-result and Player-context relationships.
- `EventTypeScope` exposes metric definitions.
- Event result reads return normalized metric payloads and Alliance result rows.

### Deliberately deferred to EC-P3

`event_player_contexts` is a final schema contract in EC-P1, but its first-write/freeze workflow is not partially implemented here. Correct historical context freezing spans registration, roster, Rally, attendance and result entry. EC-P3 will establish the one Events-owned capture contract and call it from the appropriate participation/result boundaries so context is frozen once from authoritative state rather than inconsistently copied by each workflow.

### Exit evidence

- fresh-schema feature coverage asserts all normalized tables/columns and absence of legacy JSON metric columns;
- database-level test rejects Event retargeting after creation;
- target display snapshot test proves later Alliance rename does not rewrite Event evidence;
- Kingdom Event result tests prove `alliance_id` is accepted only when `Alliance.kingdom_id` matches the Event Kingdom;
- historical Player-context tests prove represented `alliance_id` cannot disagree with `kingdom_id_at_event`; and
- architecture tests protect restrictive target retention, immutability, normalized metrics and the canonical Alliance identity.

## EC-P2 — KingShot Event metric catalogue — COMPLETE

EC-P2 establishes a typed, persisted measurement vocabulary for every supported Event Type/scope without duplicating the primary result score or inventing a universal contribution score.

### Delivered score semantics

The existing first-class `score` field remains the one canonical primary numeric result. `event_type_scopes` now records its Event-specific meaning through:

```text
result_score_label_key
result_score_unit
result_score_higher_is_better
```

This allows a Bear Hunt score to mean damage, a Swordland score to mean relic points, a Castle score to mean castle points, and ordinary scoring Events to mean points without writing the same number again as a metric row.

`rank` and `outcome` remain first-class result fields.

### Delivered component catalogue

`KingShotEventMetricCatalog` defines normalized component metrics only where the application has meaningful semantics. The catalogue includes:

- Bear Hunt Rally participation counts;
- Viking Vengeance wave/failure execution;
- phase-point breakdowns for multi-phase scoring Events;
- Swordland/Tri-Alliance/Summit battlefield kills, captures and objective occupation duration;
- Flamedragon palace occupation and Aerie capture contribution;
- Cesares Fury progression/captain participation;
- Outpost/Sanctuary objective occupation and supported Player combat contribution;
- Castle Event/Alliance/Player component facts including Carnage, Occupation and Casualty point components;
- Kingdom of Power Event/Alliance/Player phase points;
- Hero Roulette spin count; and
- conservative no-extra-metric profiles for Events where score/rank/outcome are sufficient.

Custom Events do not inherit arbitrary system component metrics.

### Metric definition semantics

Normalized definitions persist:

```text
subject                 event | alliance | player
key
label_key
unit
value_type              integer | decimal | duration | percentage
aggregation             sum | max | min | average | latest
dimension_kind          null | phase | objective
is_primary
is_contribution_metric
higher_is_better
sort_order
```

`dimension_kind` establishes the validation contract for P3. A `phase_points` value must identify a real occurrence phase; an objective-dimensioned value must identify a real occurrence objective. Arbitrary dimension strings do not become historical facts.

### Persistence/runtime integration

- canonical Event Type-scope seeding persists score semantics from the same catalogue used by runtime code;
- a greenfield metric-catalogue migration persists normalized definitions for all supported system Event Type scopes;
- `EventTypeRegistry` loads metric definitions with scope configuration;
- `EventTypeDefaultsResolver` exposes score semantics and normalized definitions to downstream Event workflows/UI;
- `EventMetricDefinition` exposes dimension semantics; and
- Event metric subjects remain `event | alliance | player`, using canonical `alliance_id` rather than a parallel Alliance identity.

### Compatibility rule

Metric compatibility normally requires:

```text
same Event Type scope
+ same subject
+ same metric key
+ compatible dimension
```

`is_contribution_metric` makes a metric reportable; it does not make unrelated Event metrics arithmetically comparable.

No `universal_contribution_score`, `total_contribution_score`, or other unexplained cross-Event total is introduced.

### Exit evidence

- unit coverage validates every supported Event Type/scope resolves a measurement profile with unique subject/key definitions;
- targeted tests protect Bear, Viking, battlefield, Castle, Kingdom of Power and conservative no-extra-metric semantics;
- persistence tests prove score semantics, subjects, dimensions and definitions survive fresh migration;
- schema coverage protects score-semantic and `dimension_kind` columns;
- architecture tests protect separation of primary score versus component metrics, canonical Alliance subject identity and absence of a universal score; and
- the living [KingShot Event metric catalogue](../event-metric-catalogue.md) documents the final P2 vocabulary and P3 capture boundary.

## EC-P3 — Capture and derivation — NEXT

Extend Event result workflows to persist normalized metrics. Derive participation/reliability facts from existing authoritative registration, attendance, roster and Rally outcomes where possible rather than requiring duplicate manual metrics.

P3 also owns occurrence-time Player context freezing at the correct first participation/result boundary.

P3 must validate `dimension_kind` against exact occurrence state and preserve metric provenance as `manual | derived | imported`.

Result mutations follow ADR 0010 and remain Events-owned.

## EC-P4 — Player history query

Build one active-Player query across all historical Event targets plus Contributions-owned non-Event records.

Filters: date, scope, Event Type, historical Alliance/Kingdom context, participation outcome, metric/category.

No current Alliance or current Kingdom filter may remove historical Player facts.

## EC-P5 — My Contributions / History UX

Create the active-Player lifetime surface with summary cards, scope tabs, chronological timeline, Event-specific metrics, reliability/participation summaries, and links into historical Event detail where authorized.

## EC-P6 — Alliance historical Event intelligence

Current authorized Alliance leadership can browse every historical Event targeted at the Alliance, inspect results/attendance/rosters/objectives/Rallies/metrics, and see former members' historical contributions.

Current affiliation may be displayed for context but never filters the historical participant set.

## EC-P7 — Kingdom historical Event intelligence

Current exact-Kingdom leadership can browse every historical Event targeted at the Kingdom, including Alliance-level and Player-level contribution breakdowns using occurrence-time representation context.

Transferred Players remain part of the old Kingdom Event record. Alliance grouping is by the canonical historical `alliance_id`; current membership does not rewrite it.

## EC-P8 — Reporting and exports

Extend Contributions reports/exports with Event source, scope, Event Type, occurrence date, historical represented Alliance/Kingdom, Player identity, metric definition/value/unit, outcome, rank, and compatible non-Event Contribution records.

## EC-P9 — Trends and comparative intelligence

Add Event Type-compatible trends, participation/reliability trends, best/latest/average metrics where meaningful, Alliance historical top contributors, and Kingdom contribution by represented Alliance.

Do not create a universal score unless a separately governed normalization model is explicitly approved.

## EC-P10 — Security, isolation and concurrency

Required coverage includes:

- Player history surviving Alliance movement;
- Player history surviving Kingdom transfer;
- new Alliance/Kingdom leaders seeing pre-tenure organization history;
- former leaders losing organization-wide access;
- old Alliance history retaining former-member results;
- old Kingdom history retaining transferred-Player results;
- Kingdom Event Alliance results rejecting Alliances from another Kingdom;
- sibling Player isolation;
- Platform Admin game-domain bypass rejection;
- historical snapshots never granting authority;
- immutable Event target enforcement; and
- result/metric uniqueness/concurrent updates.

## EC-P11 — Performance

Profile Player timeline, Alliance history and Kingdom history queries at realistic Event/result volumes. Add composite indexes and bounded pagination. Avoid N+1 current-affiliation lookups when rendering historical participant tables.

## EC-P12 — Final cleanup

Remove superseded schema, actions, query assumptions and documentation. Final docs describe only the accepted model. Run fresh database migration from zero plus full PHP/frontend/security/architecture suites.

## Definition of done

EVENT-CONTRIB-001 is complete when the application can answer both of these independently and correctly:

```text
What has this Player contributed over their lifetime?
```

and

```text
What happened historically for this Alliance or Kingdom?
```

without either answer depending on today's membership/Kingdom placement, while current authority remains the only basis for organization-wide access.
