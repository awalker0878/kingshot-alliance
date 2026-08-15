# Event contribution and historical intelligence

[← Events domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domains:** Events (facts) and Contributions (unified history/reporting)  
**Program:** EVENT-CONTRIB-001

## 1. Purpose

Defines how Player-, Alliance-, and Kingdom-scoped KingShot Events contribute to durable Player history and organization-owned historical intelligence.

The core rule is:

```text
Player history follows Player.
Alliance history follows the Event's Alliance target.
Kingdom history follows the Event's Kingdom target.
Current authority controls who may view organizational history.
Current membership never rewrites historical ownership.
```

## 2. Historical ownership model

### Player history

Every Player-specific Event result/metric is keyed to durable `player_id`. The Player's personal history remains available to that exact active Player after Alliance changes or Kingdom transfers.

A User owning multiple Players has multiple independent histories. History is never aggregated by `user_id` for authorization or identity.

### Alliance history

An Alliance-scoped Event belongs permanently to the `alliance_id` targeted when the Event was created. Current authorized Alliance leadership may inspect the complete historical Event record for that Alliance, including Players who have since left, changed Alliance, become unclaimed, or changed rank.

Historical Alliance Event queries must begin from the Event target:

```text
scope = alliance
AND alliance_id = requested alliance
```

They must not begin from the current Alliance roster or current active memberships.

### Kingdom history

A Kingdom-scoped Event belongs permanently to the `kingdom_id` targeted when the Event was created. Current authorized Kingdom leadership may inspect the complete historical Event record for that Kingdom, including Players who later transferred Kingdoms or changed Alliance.

Historical Kingdom Event queries must begin from the Event target:

```text
scope = kingdom
AND kingdom_id = requested kingdom
```

They must not filter historical participation by each Player's current `current_kingdom_id`.

Alliance-level results inside a Kingdom Event reference the canonical `Alliance` through `alliance_id`. The Alliance itself owns required `kingdom_id`, which identifies the Kingdom that Alliance belongs to. The database rejects a Kingdom Event Alliance result when `Alliance.kingdom_id` does not match the Event Kingdom.

Alliance names are display data, not identity. The same name may exist in different Kingdoms.

## 3. Current authorization versus historical ownership

Historical ownership and authorization are intentionally separate.

- A current Alliance leader may read Alliance-wide historical Event intelligence for the Alliance they currently lead.
- A former Alliance leader who leaves or loses authority loses Alliance-wide access, but keeps their own Player history.
- A newly appointed Alliance leader can read Event history from before their tenure.
- A current Kingdom leader may read Kingdom-wide historical Event intelligence for their exact Kingdom.
- A former Kingdom leader loses Kingdom-wide history access when the current role is lost, but keeps their own Player history.
- Platform Administrator status does not bypass Alliance/Kingdom Event-history authorization.

Authorization always uses the current active Player and current scope authority. Historical roster, rank, represented Alliance, or Kingdom snapshots are evidence only.

## 4. Events owns Event facts

Events remains authoritative for:

- Event target and Event Type scope;
- occurrences;
- registration/responses/attendance;
- roster and Rally participation facts exposed through supported contracts;
- Event-wide results;
- Alliance results inside Kingdom Events where supported;
- Player results;
- normalized Event metric definitions and values; and
- occurrence-time historical Player context.

Contributions does not create a second canonical copy of these facts merely to make them reportable.

## 5. Contributions owns unified history/reporting

Contributions composes:

1. Events-owned historical facts; and
2. Contributions-owned non-Event records such as manual/self-reported/calculated contribution records that are genuinely Contributions state.

The unified Player history therefore answers questions such as:

- What has this Player contributed across Player, Alliance, and Kingdom Events?
- Which Alliances or Kingdoms did the Player represent at the time?
- What non-Event Alliance contribution records also exist for the Player?

Organization reporting answers:

- What happened in every historical Event owned by this Alliance?
- Which Players contributed, including former members?
- What happened in every historical Event owned by this Kingdom?
- Which Alliances/Players contributed at the time, regardless of their current placement?

## 6. Historical Player context

Where current relationships would rewrite the meaning of the past, Events persists occurrence-time context such as:

```text
occurrence_id
player_id
player_name_snapshot
represented_alliance_id nullable
represented_alliance_name_snapshot nullable
represented_alliance_tag_snapshot nullable
kingdom_id_at_event
context_frozen_at
```

The durable `player_id` remains the Player identity. `represented_alliance_id` references the canonical Alliance identity when the Player represented an Alliance for the occurrence. `kingdom_id_at_event` records the Kingdom context. If `represented_alliance_id` is present, that Alliance's `kingdom_id` must equal `kingdom_id_at_event`.

Snapshot fields are presentation/evidence only and never grant permission.

## 7. Metric semantics

Event result measurement has two layers.

### First-class result fields

`score`, `rank`, and `outcome` remain first-class result fields. `event_type_scopes` defines the meaning of `score` for each supported Event Type/scope through:

```text
result_score_label_key
result_score_unit
result_score_higher_is_better
```

The primary score is therefore stored once rather than copied into a metric row merely to attach Event-specific semantics.

A score is comparable historically only within compatible Event Type/scope semantics; a numeric score from one Event family is not automatically comparable with another simply because both use the `score` column.

### Normalized component metrics

Additional contribution facts use `event_metric_definitions` and normalized metric value rows. A definition identifies:

- `event | alliance | player` subject;
- stable metric key;
- localization key and unit;
- integer/decimal/duration/percentage value type;
- aggregation semantics;
- optional `phase | objective` dimension kind;
- contribution/primary flags; and
- optional higher-is-better direction.

Dimensioned metrics use `dimension_key` only after P3 validates the key against the exact occurrence's phase/objective state.

Examples include Bear Hunt Rally participation, battlefield kills/captures/occupation duration, Castle point components, and Kingdom of Power phase-point breakdowns.

Universally comparable historical facts may include Event participation count, completed/absent/excused outcomes, and reliability. Numeric metrics are compared only when their Event Type scope, subject, metric definition, and dimension semantics are compatible.

The system does not create an unexplained universal total by adding unrelated scores or units.

See [KingShot Event metric catalogue](event-metric-catalogue.md).

## 8. Immutability and retention

After Event creation, scope and target identity are immutable at the database boundary. Target display snapshots are persisted from the authoritative target at creation and remain evidence only.

A Player leaving an Alliance, a Player transferring Kingdoms, a leadership transfer, an Alliance suspension/closure, or a Player becoming unclaimed does not rewrite historical Event ownership.

Completed/historical Event facts must not disappear merely because a target relationship changes. Destructive target deletion is therefore constrained by retained history rather than handled through cascade deletion of historical Event ownership/results.

## 9. Read surfaces

### Personal

`My Contributions / History` uses the currently active Player and shows that Player's complete cross-scope history. Filters include date range, Event scope, Event Type, Alliance/Kingdom at event, participation outcome, and metric.

### Alliance

Alliance Event History is authorized from the current active Player's current Alliance authority but reads all Events permanently targeted at that Alliance. Historical participant rows may show both occurrence-time affiliation and current affiliation for context; current affiliation never filters the historical result set.

### Kingdom

Kingdom Event History is authorized from current exact-Kingdom role authority and reads all Events permanently targeted at that Kingdom. Kingdom reports may group historical Player results by the canonical `alliance_id` represented at the occurrence.

## 10. Mutation and concurrency principles

Implementation follows ADR 0010. Events owns Event result/metric mutation orchestration and locks its natural Event/occurrence/result aggregates. Contributions owns its own non-Event mutation flows and read/report composition. Cross-domain reporting does not transfer mutation ownership.

## 11. Required tests

The implementation must cover:

- Player leaves Alliance and retains personal Event history;
- Player joins another Alliance and old Alliance leadership still sees old Event contribution;
- new R5/R4 with applicable permission can see historical Alliance Events from before their tenure;
- former leader loses Alliance-wide historical access;
- Player transfers Kingdom and retains personal Kingdom Event history;
- current Kingdom leadership sees historical contribution from Players who later transferred;
- Kingdom Event Alliance results reject an Alliance whose `kingdom_id` belongs to another Kingdom;
- sibling Players owned by one User remain isolated;
- current membership/current Kingdom placement never filters historical participants;
- Event target is immutable after creation;
- historical snapshots never grant authority;
- score semantics are defined per Event Type scope without duplicating the score as a component metric;
- dimensioned metric definitions validate against exact occurrence dimensions before write; and
- incompatible metric definitions cannot be aggregated as one universal score.

## 12. Greenfield implementation rule

The database is treated as empty for EVENT-CONTRIB-001. Implementation modifies the canonical migrations/schema and code directly to the final model. No compatibility columns, dual-write shims, legacy backfills, or legacy membership/User authority paths are introduced.

## 13. Related documentation

- [KingShot Event metric catalogue](event-metric-catalogue.md)
- [ADR 0011 — Historical Event and contribution ownership](../../adr/0011-event-history-and-contribution-ownership.md)
- [Event results and Player intelligence](results-and-intelligence.md)
- [Contributions](../contributions/README.md)
- [Memberships](../memberships/README.md)
- [Kingdoms](../kingdoms/README.md)
- [ADR 0010 — Transactional mutation and concurrency principles](../../adr/0010-transactional-mutation-authority.md)
