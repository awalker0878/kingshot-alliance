# Event history composition

[← Contributions domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Contributions

## 1. Purpose

Defines the Contributions-side read/report contract for composing Events-owned participation, result, and metric facts into Player, Alliance, and Kingdom contribution history.

No Events-to-Contributions reconciliation/materialization workflow exists. Events remains authoritative for Event facts; Contributions composes those facts at query/report time.

See [Event contribution and historical intelligence](../events/event-contribution-history.md) and [ADR 0011](../../adr/0011-event-history-and-contribution-ownership.md).

## 2. Scope and non-scope

In scope:

- Player lifetime contribution/history composition across Player-, Alliance-, and Kingdom-scoped Events;
- Alliance historical Event contribution reporting from Events permanently targeted at that Alliance;
- Kingdom historical Event contribution reporting from Events permanently targeted at that Kingdom;
- composition with Contributions-owned non-Event records; and
- compatible Event metric aggregation and report/export projection.

Out of scope:

- copying Event attendance/results/metrics into Contribution records merely for reporting;
- editing Event facts;
- using current Alliance membership/current Kingdom placement as historical identity;
- destructive rewriting of historical Event context; and
- combining incompatible Event metrics into an unexplained universal score.

## 3. Model and state

Events owns Event/occurrence/participation/result/metric rows. Contributions owns its non-Event categories/records and unified history/report queries.

The durable historical axes are:

```text
player_id                  → Player lifetime history
event.alliance_id          → Alliance-owned Event history
event.kingdom_id           → Kingdom-owned Event history
```

Current membership is authority/eligibility context only. It is never the key used to decide whether an old Event contribution exists.

## 4. Invariants

1. Events is authoritative for Event participation, attendance, results, metrics, and historical Event context.
2. Contributions never mutates Events persistence through history/reporting.
3. Player history follows durable `player_id` across Alliance and Kingdom changes.
4. Alliance historical reporting includes all qualifying Events targeted at the Alliance, including results for Players who later leave.
5. Kingdom historical reporting includes all qualifying Events targeted at the Kingdom, including Players who later transfer.
6. Current scope authority controls organization-wide visibility.
7. Historical Player/Alliance/Kingdom context snapshots never grant authority.
8. Compatible metrics are identified by stable Event Type scope + metric definition/key before aggregation.
9. Non-Event Contribution records retain their own approval/correction/reversal lifecycle and are not confused with Events-owned facts.

## 5. Workflows

### Personal history

The active Player requests history. Contributions composes:

- Events-owned Event facts where the result/participation subject is that exact `player_id`; and
- Contributions-owned non-Event records where `player_id` matches.

No current Alliance filter is applied to historical Event facts.

### Alliance history

The current active Player is authorized against the requested Alliance. The read then selects historical Events whose immutable target is that Alliance. Historical participant rows remain visible even if those Players are no longer members.

### Kingdom history

The current active Player is authorized against the exact Kingdom. The read then selects historical Events whose immutable target is that Kingdom. Historical participant rows remain visible even if those Players later transfer Kingdoms.

### Reports and exports

Reports may project Event scope/type/date, historical represented Alliance/Kingdom context, Player identity, metric key/value/unit, outcome, rank, and non-Event Contribution records. Reports never copy those Event facts into `contribution_records` simply to make them reportable.

## 6. Authorization, tenancy and privacy

Personal history requires ownership of the exact active Player. Alliance-wide history requires current authority for the exact Alliance. Kingdom-wide history requires current authority for the exact Kingdom.

A former leader loses organization-wide access when their current authority is removed. A new leader inherits access to organization-owned history from before their tenure. Platform Administrator status provides no game-domain history bypass.

## 7. Persistence and query semantics

Contributions persistence is used only for Contributions-owned state. Event history is queried from Events-owned persistence through supported contracts/queries.

Historical organization queries begin from immutable Event target identity, not from current membership tables.

Cross-Event numeric aggregation is allowed only for compatible metric definitions. Universally meaningful derived history such as participation counts and reliability may span Event types when its semantics are explicitly defined.

## 8. Events, integrations and background processing

History composition is primarily a read/report concern. Large report generation may be queued, but queueing does not transfer Event fact ownership or introduce a reconciliation ledger.

Event mutations continue to emit Events-owned audit/outbox evidence. Contributions-owned report/export actions emit Contributions-owned evidence.

## 9. Failure, idempotency and concurrency

- unauthorized target history fails closed;
- sibling Player history never leaks through shared `user_id`;
- stale current membership cannot remove historical Event facts from an authorized organizational report;
- report retries use normal deterministic report identity/idempotency where applicable; and
- history reads do not acquire broad mutation locks merely to join domain-owned facts.

## 10. Operations and observability

Reports should identify source domain (`event` or `contribution`), source Event/occurrence when applicable, immutable Event scope/target, durable Player ID, metric/category, and historical context needed to explain the row.

Source corrections are made in the owning domain. Do not directly edit Contributions persistence to imitate a corrected Event fact.

## 11. Tests and validation

Tests cover:

- Player history after Alliance change;
- Player history after Kingdom transfer;
- old Alliance leadership history including former members;
- old Kingdom history including transferred Players;
- new leader access to pre-tenure organization history;
- former leader loss of organization-wide access;
- sibling Player isolation;
- immutable-target query semantics; and
- compatible metric aggregation only.

## 12. Related documentation

- [Contributions domain](README.md)
- [Event contribution and historical intelligence](../events/event-contribution-history.md)
- [Events domain](../events/README.md)
- [ADR 0011](../../adr/0011-event-history-and-contribution-ownership.md)
- [Authorization](../authorization/README.md)
