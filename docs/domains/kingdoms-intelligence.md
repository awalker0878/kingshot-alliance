# Kingdoms roster intelligence

[← Kingdoms player snapshots](kingdoms-snapshots.md)

**Status:** Accepted as part of `KINGDOMS-001`  
**Scope:** [Kingdoms roster intelligence increment](../product/kingdoms-roster-intelligence-increment.md)  
**Acceptance evidence:** [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md)

This guide defines the current calculation and visibility contract. Intelligence is computed from alliance-owned roster state and recorded snapshots at read time. It does not introduce another persistence source for current power, a scheduled calculation job, member scoring, or cross-alliance aggregation.

## Current-metric universe

Current roster intelligence uses roster entries whose state is `active` or `tracked`.

Entries marked `left` are excluded from current power, linkage, snapshot-quality and trend aggregates. A recently-left entry can still contribute to the recent-departure count because that metric describes roster movement rather than current membership.

## Recorded power semantics

For each active/tracked roster entry, the latest snapshot by capture time is the current recorded observation.

Current recorded-power aggregates include players with a latest snapshot even when that snapshot is stale. Staleness is surfaced separately so the dashboard does not silently discard recorded values or imply that old data is fresh.

Players with no snapshot are **missing**, not zero, and are excluded from total/average/median calculations. A recorded power value of `0` remains a real numeric observation and participates in aggregates.

### Exact arithmetic

Individual snapshots use signed 64-bit integer power. Alliance totals and accumulated trend deltas can exceed one signed 64-bit value when many players are summed, so the implementation uses decimal-string arithmetic rather than floating-point conversion.

Dashboard power values and deltas remain decimal strings through the server/browser boundary. The browser formats those strings for display and does not coerce them to JavaScript `Number` values.

Current calculations:

- **total recorded power** — exact sum of latest recorded powers;
- **average recorded power** — exact sum divided by recorded-player count, rounded to the nearest whole power;
- **median recorded power** — middle value after numeric ordering; an even-sized set may produce an exact `.5` result.

When no player has a snapshot, total, average and median are missing rather than zero.

## Snapshot quality

The accepted snapshot freshness threshold is:

- **current** — latest snapshot captured within 30 days;
- **stale** — history exists but the latest snapshot is older than 30 days;
- **missing** — no snapshot exists.

These counts cover active/tracked roster entries only. Manual and CSV snapshots participate in the same history/projection contract.

## Recent roster movement

The current recent-roster window is seven days:

- joins count active/tracked entries with a recorded joined date in the window;
- departures count `left` entries whose left timestamp is in the window.

Missing joined dates are not inferred from roster-row creation time.

## Membership linkage coverage

Linkage coverage is calculated over active/tracked roster entries:

`linked roster entries / active-or-tracked roster entries`

The displayed percentage uses one decimal place. When there are no active/tracked roster entries, percentage is missing rather than fabricated as `0%`.

## Trend-window contract

The accepted implementation computes aggregate 7-day and 30-day power change without interpolation.

For an N-day comparison:

1. current power is the roster entry's latest snapshot;
2. target time is `as-of - N days`;
3. baseline is the closest recorded snapshot **at or before** that target;
4. baseline must not be older than `as-of - 2N days`;
5. current capture time must be later than baseline capture time; and
6. if any condition is not satisfied, that player is not comparable for that window.

Therefore:

- a snapshot captured five days ago cannot stand in for a seven-day baseline;
- a year-old snapshot cannot stand in for a 30-day baseline;
- an observation is never interpolated between two captures; and
- a lone stale snapshot cannot compare against itself and create a false zero change.

The aggregate trend is the exact signed sum of comparable-player deltas. Every trend card includes its comparable-player count so readers can judge how much historical coverage supports the number.

## Missing data versus zero

This distinction is mandatory throughout the dashboard:

- missing total/average/median → no recorded current powers;
- missing trend → no comparable players for that window;
- zero total/delta → an actual calculated zero from recorded/comparable observations;
- missing snapshot → no observation exists;
- zero snapshot power → a recorded observation exists with value zero.

UI and future consumers must not collapse these states.

## Visibility and authorization

Aggregate roster intelligence uses ordinary authenticated roster visibility: active authenticated Alliance context plus `alliance.view`.

Individual comparison detail is management-only and requires `kingdoms.manage`.

The member response does not contain individual comparison rows, manager notes, membership emails/IDs, snapshot actor identity or import-management metadata.

The manager comparison table is deliberately alphabetical by player display name. It is not sorted by growth/decline, does not label winners/losers, and does not assign a score.

## Manager comparison detail

For each active/tracked player, authorized managers may see:

- snapshot quality state;
- whether an application membership is linked;
- current recorded power and capture time;
- eligible 7-day baseline/current captures and signed change; and
- eligible 30-day baseline/current captures and signed change.

Insufficient history is displayed as insufficient history rather than `0` change.

This view exists for data-quality and roster-management diagnosis. It must not become an automatic punitive recommendation or member-performance ranking.

## Contributions boundary

Power growth is a game observation and **not** a Contribution record.

The implementation does not write Contribution data, award contribution credit, assign member scores, or feed disciplinary workflows from power change. Any future cross-domain use requires an explicit supported contract rather than reading Kingdoms persistence internals.

## Runtime, query and operational model

Intelligence is calculated synchronously when the dashboard is requested.

The accepted implementation introduces:

- no intelligence database table;
- no mutable aggregate/cache table;
- no queue job;
- no scheduler command; and
- no configuration variable.

Historical snapshots remain the calculation source of truth. Latest/current and trend-baseline queries are batched under the active Alliance rather than executed one player at a time.

The `K1-P6` performance regression seeds 150 tracked players with 450 snapshots and asserts the calculation remains within a fixed bounded SELECT-query budget. This protects query shape/N+1 behavior; it is not a production capacity benchmark.

See [Kingdoms roster intelligence operations](../operations/kingdoms-roster-intelligence.md) for diagnostic and rollback guidance.

## Tenant isolation

All input records are selected under the active Alliance:

- roster query starts with `alliance_id`;
- latest snapshots are constrained by Alliance and eligible roster IDs;
- trend baselines are constrained by Alliance and eligible roster IDs; and
- no Kingdom/KingdomPlayer-only query authorizes or aggregates tenant data.

Another alliance in the same Kingdom cannot affect or appear in the current alliance's totals, trends or manager detail.

## Related accepted contracts and boundaries

- [Kingdoms roster](kingdoms-roster.md)
- [Kingdoms player snapshots](kingdoms-snapshots.md)
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md)
- [Whole-increment security review](../security/kingdoms-roster-intelligence-security-review.md)

Automatic ranking/scoring, disciplinary recommendations, contribution scoring, public Kingdoms API/webhook exposure, cross-alliance/kingdom-wide intelligence, transfer/diplomacy workflows and automated game-data ingestion remain outside `KINGDOMS-001`.
