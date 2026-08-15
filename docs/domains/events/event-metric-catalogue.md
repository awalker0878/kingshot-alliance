# KingShot Event metric catalogue

[← Events domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Events  
**Program:** EVENT-CONTRIB-001 / EC-P2

## 1. Purpose

Defines the measurement vocabulary for supported KingShot Event Type/scope combinations.

The catalogue separates:

1. **first-class result fields** — `score`, `rank`, and `outcome`; and
2. **normalized component metrics** — additional facts that explain contribution inside an Event.

A primary Event score is stored once. It is not copied into `event_*_result_metrics` merely to attach a label or unit.

## 2. Score semantics

Each `event_type_scope` may define:

```text
result_score_label_key
result_score_unit
result_score_higher_is_better
```

These fields explain what the existing result `score` means for that Event Type/scope.

Examples include damage, battle/relic points, castle points, or ordinary points. `rank` and `outcome` remain separate first-class result fields.

A scope with no meaningful score may leave the score metadata null.

## 3. Component metric definition

Each normalized metric definition contains:

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

A matching key in another Event Type scope does not make values automatically comparable.

## 4. Subjects

- `event` — an occurrence-wide fact.
- `alliance` — an Alliance result/fact inside a Kingdom Event, using canonical `alliance_id`.
- `player` — a durable Player result/fact using `player_id`.

There is no parallel GameAlliance/KingdomAlliance metric subject.

## 5. Dimensions

Dimensioned metrics intentionally reuse one stable metric definition for named subcomponents.

### `phase`

`phase_points` uses the Event phase key as `dimension_key`.

Example:

```text
metric = phase_points
dimension_kind = phase
dimension_key = preparation
value = ...
```

### `objective`

`objective_occupation_seconds` uses the Event objective key/identifier as `dimension_key`.

P3 validates dimension keys against the exact occurrence before accepting a value. Arbitrary dimension strings are not authoritative.

## 6. System measurement profiles

The following component metrics are predefined by `KingShotEventMetricCatalog`. Score/rank/outcome remain available independently where the Event supports them.

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

## 7. Conservative catalogue rule

The system does not invent a component metric merely because an Event exists.

Where the application only has a trustworthy score/rank/outcome result, those first-class fields are sufficient. Additional metrics are introduced only when their meaning, subject, unit, and aggregation semantics are explicit enough to persist and report consistently.

Custom Events therefore do not inherit arbitrary system component metrics. A future custom-metric workflow must validate and govern its own definitions rather than overloading the KingShot system catalogue.

## 8. Contribution semantics

`is_contribution_metric` means the metric may participate in contribution history/reporting. It does not imply that values from different Event Type scopes can be summed together.

The system may compare or trend a metric when the comparison uses compatible definition semantics, normally:

```text
same Event Type scope
+ same subject
+ same metric key
+ compatible dimension
```

Cross-Event normalization, if ever introduced, requires a separately approved model. EC-P2 creates no universal contribution score.

## 9. Aggregation semantics

- `sum` — additive observations within the compatible grouping.
- `max` — furthest/best reached value where that meaning is explicit.
- `min` — lower observed value when a metric requires it.
- `average` — arithmetic mean for compatible observations.
- `latest` — most recent authoritative value.

`higher_is_better` is presentation/comparison metadata only. Null means the direction is not safely reducible to better/worse.

## 10. Provenance

Metric values record one of:

```text
manual
derived
imported
```

P2 defines the vocabulary only. P3 owns the write paths and determines which source is valid for each capture workflow.

Derived facts should be produced from existing authoritative Event state where possible instead of asking a manager to enter the same fact twice.

## 11. Localization

Metric definitions store stable localization keys such as:

```text
events.metrics.damage
events.metrics.rallies_joined
events.metrics.phase_points
```

The metric key is domain identity; translated labels are presentation. Adding or changing a translation never changes metric identity or historical meaning.

## 12. Mutation ownership

Events owns metric definition/value persistence and capture workflows. Contributions consumes the supported read model for unified history/reporting but does not mutate Event metrics or copy them into a second Event ledger.

All metric/result mutations follow ADR 0010 and use Events-owned mutation boundaries and natural result aggregates.

## 13. Next phase

EC-P3 will:

- validate and persist normalized metric values;
- validate `phase` and `objective` dimensions against the exact occurrence;
- derive supported participation/Rally/attendance facts from authoritative Event state;
- freeze occurrence-time Player context once from authoritative identity/Alliance/Kingdom state; and
- retain manual/imported provenance where those capture modes are supported.

## 14. Related documentation

- [Event contribution and historical intelligence](event-contribution-history.md)
- [Event results and Player intelligence](results-and-intelligence.md)
- [EVENT-CONTRIB-001 implementation plan](product/event-contribution-history-implementation-plan.md)
- [ADR 0010 — Transactional mutation and concurrency principles](../../adr/0010-transactional-mutation-authority.md)
- [ADR 0011 — Historical Event and contribution ownership](../../adr/0011-event-history-and-contribution-ownership.md)
