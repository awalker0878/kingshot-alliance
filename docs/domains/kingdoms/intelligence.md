# Kingdoms roster intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as part of `KINGDOMS-001`  
**Owning domain:** `Kingdoms`

## 1. Purpose

This document defines the current roster calculation and visibility contract. Intelligence is computed from Alliance-owned roster state and recorded snapshots at read time. It does not introduce a second persistence source for current power, a scheduled calculation job, member scoring, or cross-Alliance aggregation.

## 2. Scope and non-scope

In scope:

- current roster metric universe;
- exact recorded-power total/average/median;
- current/stale/missing data quality;
- recent roster movement;
- application-membership linkage coverage;
- bounded 7-day/30-day power trends; and
- manager-only factual comparison detail.

Out of scope:

- player punishment/value scoring;
- automatic recommendations;
- Contribution scoring from power growth;
- cross-Alliance/global rankings; and
- public Kingdoms API/webhook exposure.

## 3. Model and state

Current intelligence considers roster entries in `active` or `tracked` state. `left` entries are excluded from current power/linkage/quality/trend aggregates, though recently left entries may contribute to recent-departure counts.

For each active/tracked entry, the latest snapshot by capture-time contract is the current recorded observation.

Players with no snapshot are **missing**, not zero, and are excluded from total/average/median. A recorded `0` power remains a real observation and participates in calculations.

## 4. Invariants

1. Historical snapshots remain the source of truth for intelligence.
2. Current recorded-power aggregates may include stale latest values, but staleness is surfaced separately.
3. Missing power is distinct from recorded zero.
4. Power arithmetic remains exact decimal-string arithmetic rather than floating-point conversion.
5. No trend baseline is interpolated or extrapolated.
6. Unsupported/missing baseline yields missing/insufficient history, not zero change.
7. Aggregate trend includes only comparable players and exposes comparable-player count.
8. Member output excludes manager-only individual comparison detail/private provenance.
9. Manager detail remains factual/alphabetical, not winner/loser ranking.
10. Intelligence writes no Contribution record or punitive score.

## 5. Workflows

### Current recorded-power aggregates

- **Total recorded power** — exact sum of latest recorded powers.
- **Average recorded power** — exact sum divided by recorded-player count, rounded to nearest whole power.
- **Median recorded power** — middle numeric value; even sets may yield exact `.5`.

When no player has a snapshot, total/average/median are missing rather than zero.

### Snapshot quality

For active/tracked roster entries:

- **current** — latest snapshot within 30 days;
- **stale** — history exists, latest older than 30 days;
- **missing** — no snapshot.

### Recent roster movement

Current window is seven days:

- joins count active/tracked entries with known joined date in the window;
- departures count `left` entries with left timestamp in the window.

Missing joined date is not inferred from row creation time.

### Membership linkage coverage

Calculated over active/tracked roster entries:

```text
linked roster entries / active-or-tracked roster entries
```

Displayed percentage uses one decimal place. With no active/tracked entries, the percentage is missing rather than fabricated `0%`.

### Trend-window contract

For N days:

1. current power is the roster entry's latest snapshot;
2. target = `as-of - N days`;
3. baseline = closest recorded snapshot at or before target;
4. baseline must not be older than `as-of - 2N days`;
5. current capture must be later than baseline capture; and
6. otherwise the player is not comparable.

Therefore a 5-day-old snapshot cannot stand in for a 7-day baseline; a year-old snapshot cannot stand in for a 30-day baseline; a lone stale snapshot cannot compare with itself to produce false zero.

### Manager comparison detail

Authorized managers may see snapshot quality, linkage, current power/capture, eligible 7-day baseline/current/change, and eligible 30-day baseline/current/change. Insufficient history is displayed explicitly.

## 6. Authorization, tenancy and privacy

Aggregate roster intelligence uses authenticated active-Alliance context plus `alliance.view`.

Individual comparison detail requires `kingdoms.manage`.

Member response excludes individual comparison rows, manager notes, membership emails/IDs, snapshot actor identity, and import-management metadata.

Every source query begins with active `alliance_id`; a shared Kingdom/Player never authorizes aggregation across tenants.

## 7. Persistence and query semantics

Intelligence is calculated synchronously and introduces:

- no intelligence table;
- no mutable aggregate/cache table;
- no queue job;
- no scheduler command; and
- no configuration variable.

Latest/current and trend-baseline queries are batched under the active Alliance rather than run one-player-at-a-time.

Individual snapshots use signed 64-bit power, while Alliance totals/deltas can exceed one signed 64-bit value; decimal-string arithmetic preserves exact values through server/browser boundaries.

## 8. Events/integrations/background processing

Read-only intelligence emits no mutation audit/outbox event. There is no intelligence scheduler/job.

No public Kingdoms intelligence API/webhook contract is accepted.

## 9. Failure, idempotency and concurrency

- No snapshots → metric missing, not zero.
- No comparable baseline → trend missing/insufficient history, not zero.
- Recorded zero remains zero.
- Stale latest values remain recorded values with explicit stale quality.
- Batched tenant-first queries avoid N+1 behavior.

## 10. Operations and observability

The `K1-P6` regression seeds 150 tracked players with 450 snapshots and asserts a fixed bounded SELECT-query budget. This protects query shape/N+1 behavior; it is not a production capacity benchmark.

See [Kingdoms roster intelligence operations](operations/kingdoms-roster-intelligence.md).

## 11. Tests and validation

Accepted validation covers exact arithmetic, missing-vs-zero semantics, freshness, movement/linkage, bounded 7/30-day trends, comparable counts, member/manager privacy, tenant isolation, and realistic-volume query shape.

See the [KINGDOMS-001 exit report](product/kingdoms-roster-intelligence-exit-report.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Roster](roster.md)
- [Snapshots](snapshots.md)
- [Controlled CSV migration](csv-migration.md)
- [KINGDOMS-001 security review](security/kingdoms-roster-intelligence-security-review.md)
