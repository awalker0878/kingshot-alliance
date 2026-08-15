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

## Phase status

| Phase | Scope | Status |
| --- | --- | --- |
| EC-P0 | Final ownership, authorization, history and metric semantics | **Complete** |
| EC-P1 | Greenfield schema redesign and immutable historical targets | **Complete** |
| EC-P2 | KingShot Event metric catalogue | **Next** |
| EC-P3 | Result/metric capture and derived participation facts | Planned |
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

- `events.scope` plus the exact `player_id | alliance_id | kingdom_id` target are immutable after creation at the database boundary.
- Event target foreign keys use restrictive retention semantics rather than cascading away historical ownership.
- `events.target_display_name` and `events.target_secondary_label` preserve creation-time display evidence independently of current names.
- `event_occurrences.event_id` uses restrictive deletion semantics so result-bearing historical occurrences cannot be silently erased with their Event.
- `event_metric_definitions` defines stable metrics by Event Type scope and structural subject.
- Metric subjects are `event`, `kingdom_alliance`, and `player`.
- `event_result_metrics`, `event_kingdom_alliance_result_metrics`, and `event_player_result_metrics` store typed numeric values with definition, optional dimension, provenance source, recorder and timestamp.
- Legacy result-level JSON `metrics` columns and write parameters are removed rather than retained as compatibility fields.
- `event_kingdom_alliance_results` records represented game-Alliance results inside Kingdom Events by neutral Kingdoms-owned `KingdomAlliance` identity, including frozen name/tag evidence.
- `event_player_contexts` stores durable Player identity plus occurrence-time name, Kingdom, optional platform Alliance link, optional neutral `KingdomAlliance` link, and frozen represented-Alliance name/tag evidence.
- Natural uniqueness is enforced for occurrence result, occurrence + Player, occurrence + `KingdomAlliance`, occurrence + Player context, and metric definition/dimension per result.
- History-oriented indexes cover Player history, represented Alliance/`KingdomAlliance`, Kingdom-at-event, metric definition and occurrence-result access.

### Runtime/model alignment

- Event creation snapshots target display metadata from the current authoritative target inside the creation transaction.
- `EventResult` and `EventPlayerResult` expose normalized metric relations instead of JSON metrics.
- `EventOccurrence` exposes Event-wide, `KingdomAlliance`, Player-result and Player-context relationships.
- `EventTypeScope` exposes metric definitions.
- Event result reads return normalized metric payloads and Kingdom Event `KingdomAlliance` result rows.

### Deliberately deferred to EC-P3

`event_player_contexts` is a final schema contract in EC-P1, but its first-write/freeze workflow is not partially implemented here. Correct historical context freezing spans registration, roster, Rally, attendance and result entry. EC-P3 will establish the one Events-owned capture contract and call it from the appropriate participation/result boundaries so context is frozen once from authoritative state rather than inconsistently copied by each workflow.

### Exit evidence

- fresh-schema feature coverage asserts all normalized tables/columns and absence of legacy JSON metric columns;
- database-level test rejects Event retargeting after creation;
- target display snapshot test proves later Alliance rename does not rewrite Event evidence;
- architecture tests protect restrictive target retention, immutability trigger, normalized metrics, neutral `KingdomAlliance` identity and occurrence-time Player context.

## EC-P2 — KingShot Event metric catalogue

Define metrics per supported Event Type scope, including subject, key, unit/value type, aggregation rule, contribution relevance, display metadata, and compatibility semantics.

Initial families include Bear Hunt, Viking Vengeance, Alliance Mobilization, Alliance Championship, Alliance Brawl, Swordland Showdown/Summit League, Tri-Alliance Clash, Flamedragon Tyrant, Cesares Fury, Outpost/Sanctuary/Castle battles, Kingdom of Power, Hall of Governors, Armament Competition, Fishing Tournament, Treasure Raiders, Merchant Empire, Eternity's Reach, and Custom Events.

P2 seeds definitions only for facts the application can meaningfully represent. It does not invent scores or units where KingShot/Event capability semantics do not support them.

## EC-P3 — Capture and derivation

Extend Event result workflows to persist normalized metrics. Derive participation/reliability facts from existing authoritative registration, attendance, roster and Rally outcomes where possible rather than requiring duplicate manual metrics.

P3 also owns occurrence-time Player context freezing at the correct first participation/result boundary.

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

Current exact-Kingdom leadership can browse every historical Event targeted at the Kingdom, including `KingdomAlliance`-level and Player-level contribution breakdowns using occurrence-time representation context.

Transferred Players remain part of the old Kingdom Event record. Represented game Alliances do not need to be platform tenant Alliances to remain visible historically.

## EC-P8 — Reporting and exports

Extend Contributions reports/exports with Event source, scope, Event Type, occurrence date, historical represented Alliance/Kingdom, Player identity, metric definition/value/unit, outcome, rank, and compatible non-Event Contribution records.

## EC-P9 — Trends and comparative intelligence

Add Event Type-compatible trends, participation/reliability trends, best/latest/average metrics where meaningful, Alliance historical top contributors, and Kingdom contribution by represented `KingdomAlliance`.

Do not create a universal score unless a separately governed normalization model is explicitly approved.

## EC-P10 — Security, isolation and concurrency

Required coverage includes:

- Player history surviving Alliance movement;
- Player history surviving Kingdom transfer;
- new Alliance/Kingdom leaders seeing pre-tenure organization history;
- former leaders losing organization-wide access;
- old Alliance history retaining former-member results;
- old Kingdom history retaining transferred-Player results;
- Kingdom Event results retaining non-tenant `KingdomAlliance` identity;
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
