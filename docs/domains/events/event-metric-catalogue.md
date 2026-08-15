# KingShot Event metric catalogue

[← Events domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Events

## 1. Purpose

Defines the measurement vocabulary for supported KingShot Event Type/scope combinations under `EVENT-CONTRIB-001 / EC-P2`.

The catalogue separates first-class result fields (`score`, `rank`, and `outcome`) from normalized component metrics that explain contribution inside an Event. A primary Event score is stored once and is not copied into `event_*_result_metrics` merely to attach a label or unit.

## 2. Scope and non-scope

In scope:

- Event-Type/scope-specific meaning for the first-class `score` field;
- normalized Event, Alliance, and Player component metric definitions;
- units, value types, aggregation rules and comparison direction;
- optional phase/objective dimensions;
- contribution/reporting relevance; and
- stable localization keys.

Out of scope for EC-P2:

- writing metric values;
- deriving metrics from participation/Rally/attendance state;
- occurrence-time Player-context freezing;
- custom manager-defined metric schemas;
- user-facing metric history/result UI and its translated labels;
- cross-Event normalization; and
- any universal contribution score.

Those capture/derivation concerns begin in EC-P3. User-facing metric localization is delivered with the UI that consumes the stable keys rather than adding unused translated strings to every locale during P2.

## 3. Model and state

### First-class score semantics

Each `event_type_scope` may define:

```text
result_score_label_key
result_score_unit
result_score_higher_is_better
```

These fields explain what the existing result `score` means for that Event Type/scope. `rank` and `outcome` remain separate first-class result fields. A scope with no meaningful score leaves score metadata null.

### Component metric definitions

Each `event_metric_definition` contains:

```text
event_type_scope_id
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

Metric identity is:

```text
Event Type scope + subject + key
```

A matching key in another Event Type scope does not make the values automatically comparable.

### Subjects

- `event` — occurrence-wide fact;
- `alliance` — Alliance result/fact inside a Kingdom Event using canonical `alliance_id`; and
- `player` — durable Player result/fact using `player_id`.

There is no parallel GameAlliance/KingdomAlliance metric subject.

### Dimensions

Dimensioned metrics reuse one stable definition for named subcomponents.

`phase_points` uses `dimension_kind = phase`; its value rows use an occurrence phase key as `dimension_key`.

`objective_occupation_seconds` uses `dimension_kind = objective`; its value rows use an exact occurrence objective key/identifier as `dimension_key`.

P3 validates those keys against the exact occurrence before accepting a value.

## 4. Invariants

1. `score`, `rank`, and `outcome` remain first-class results and are not duplicated as component metrics.
2. Metric subject is exactly `event | alliance | player`.
3. Alliance metric identity uses canonical `alliance_id`.
4. A metric definition is unique by Event Type scope + subject + key.
5. Dimension kind is part of metric semantics; arbitrary dimension strings are not authoritative.
6. `is_contribution_metric` makes a metric reportable but does not make unrelated Event metrics comparable.
7. Compatible comparison normally requires the same Event Type scope, subject, metric key, and compatible dimension.
8. `higher_is_better = null` means the metric cannot safely be reduced to a better/worse direction.
9. The system never creates an unexplained universal cross-Event contribution score.
10. Custom Events do not inherit arbitrary KingShot component metrics.

## 5. Workflows

`KingShotEventMetricCatalog` resolves a measurement profile for every supported Event Type/scope in the existing Event Type catalogue.

The greenfield catalogue migration persists:

1. score semantics on `event_type_scopes`; and
2. normalized component definitions in `event_metric_definitions`.

Runtime Event Type resolution loads the persisted definitions and score semantics so future capture and UI code consume the database-backed contract instead of implementing separate switches.

### System measurement profiles

| Event Type | Scope | Score meaning | Normalized component metrics |
| --- | --- | --- | --- |
| Bear Hunt | Alliance | Damage | Player rallies joined; Player rallies led |
| Viking Vengeance | Alliance | Points | Player waves defended; Player defense failures |
| Alliance Mobilization | Alliance | Points | Player phase points |
| Alliance Championship | Alliance | Points | Player round wins |
| Alliance Brawl | Alliance | Points | Player phase points |
| Swordland Showdown | Alliance | Relic points | Player kills; objective captures; objective occupation duration |
| Tri-Alliance Clash | Alliance | Battle points | Player kills; objective captures; objective occupation duration |
| Flamedragon Tyrant | Alliance | Personal points | Player palace occupation duration; Aerie captures |
| Swordland Summit League | Alliance | Battle points | Player kills; objective captures; objective occupation duration |
| Cesares Fury | Alliance | Points | Player stages cleared; captain participations |
| Outpost Battle | Alliance | Points | Event objective occupation duration |
| Sanctuary Battle | Alliance | Points | Event objective occupation duration; Player enemy troops defeated |
| Castle Battle | Alliance | Castle points | Event objective occupation duration; Player Carnage, Occupation and Casualty point components |
| Castle Battle | Kingdom | Castle points | Event objective occupation duration; Alliance and Player Carnage, Occupation and Casualty point components |
| Kingdom of Power | Kingdom | Total points | Event, Alliance and Player phase points |
| Hall of Governors | Player | Points | Player phase points |
| Armament Competition | Player | Points | Player phase points |
| Hero Roulette | Player | none | Player spins |
| Fishing Tournament | Player | Points | no predefined component metric |
| Treasure Raiders | Player | Points | Player phase points |
| Merchant Empire | Player | Points | Player phase points |
| Eternity's Reach | Player | Points | Player phase points |
| Custom | Player / Alliance / Kingdom | Points when used | no predefined system component metrics |

The catalogue is intentionally conservative. If the application only has a trustworthy score/rank/outcome result for an Event, those first-class fields are sufficient.

## 6. Authorization, tenancy and privacy

Metric definitions do not grant authority.

- Player metric visibility follows the owning Event/history authorization contract.
- Alliance metrics inside Kingdom Events use canonical Alliance identity but are visible only through authorized Kingdom or other explicitly supported historical surfaces.
- historical metric values never grant Alliance membership, Kingdom roles, or Event permissions;
- Platform Administrator status does not bypass game-domain Event history authorization; and
- localization labels, units, rankings, or historical snapshots are never security state.

Metric mutations in P3 use the current active Player and the Events-owned mutation authority appropriate to the exact Event scope.

## 7. Persistence and query semantics

Score semantics are stored on `event_type_scopes`:

```text
result_score_label_key
result_score_unit
result_score_higher_is_better
```

Component definitions are stored in `event_metric_definitions` and later values live in the subject-appropriate normalized metric table.

Aggregation meanings are:

- `sum` — additive observations within a compatible grouping;
- `max` — furthest/highest reached value where explicitly meaningful;
- `min` — lower observed value where explicitly meaningful;
- `average` — arithmetic mean over compatible observations; and
- `latest` — most recent authoritative value.

Query/report code may compare or trend a metric only when definition semantics are compatible. Cross-Event normalization, if ever added, requires its own governed model.

Metric definitions use stable localization keys such as:

```text
events.metrics.damage
events.metrics.rallies_joined
events.metrics.phase_points
```

Metric keys are domain identity; translated labels are presentation only. The supported locale catalogues may use the existing English fallback until the result/history surfaces introduce the visible labels.

## 8. Events, integrations and background processing

Events owns metric definitions and metric-value capture. Contributions consumes the supported read model for unified history/reporting but does not mutate Event metrics or copy them into another Event ledger.

Metric values carry provenance:

```text
manual
derived
imported
```

P2 defines the source vocabulary only. P3 decides which provenance is valid for each capture path.

Derived facts should come from existing authoritative Event state where possible instead of requiring duplicate manual entry. Integrations may later provide imported values through an explicit Events-owned contract; imported input does not bypass validation of Event scope, subject, definition, dimension, or target identity.

## 9. Failure, idempotency and concurrency

- unknown Event Type/scope catalogue combinations fail closed at persistence/resolution boundaries;
- duplicate Event Type scope + subject + metric-key definitions are rejected by database uniqueness;
- invalid subject/value-type/aggregation vocabularies are rejected by application enum contracts;
- P3 must reject a phase/objective dimension that does not exist in the exact occurrence;
- metric result/value writes will lock their natural result aggregate rather than a global metric mutex;
- retries of the same logical metric write must be idempotent on result + definition + dimension; and
- incompatible metrics are never silently summed into a common total.

## 10. Operations and observability

Operational diagnostics should identify Event Type slug, scope, metric subject/key, dimension kind/key when applicable, provenance source, actor Player for human writes, and success/failure outcome.

Logs and telemetry should avoid dumping private notes or unrelated participation payloads. Catalogue synchronization/migration failure is a deployment failure because result semantics must not become partially defined.

## 11. Tests and validation

Coverage protects:

- every supported Event Type/scope resolving a measurement profile;
- subject/key uniqueness within a profile;
- Bear Hunt damage/Rally semantics;
- Viking wave/failure semantics;
- battlefield kills/capture/occupation semantics;
- Castle Event/Alliance/Player point components;
- Kingdom of Power phase dimensions for Event/Alliance/Player subjects;
- conservative no-extra-component behavior for Custom and Fishing;
- persisted score semantics and metric definitions after fresh migration;
- `dimension_kind` persistence;
- canonical `alliance` metric subject with no KingdomAlliance history identity; and
- absence of a universal contribution score.

P3 adds write-time, derivation, dimension-validation, idempotency and concurrency coverage.

## 12. Related documentation

- [Event contribution and historical intelligence](event-contribution-history.md)
- [Event results and Player intelligence](results-and-intelligence.md)
- [EVENT-CONTRIB-001 implementation plan](product/event-contribution-history-implementation-plan.md)
- [ADR 0010 — Transactional mutation and concurrency principles](../../adr/0010-transactional-mutation-authority.md)
- [ADR 0011 — Historical Event and contribution ownership](../../adr/0011-event-history-and-contribution-ownership.md)
