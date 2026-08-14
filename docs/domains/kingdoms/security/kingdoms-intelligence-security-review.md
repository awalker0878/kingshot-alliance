# Kingdoms intelligence security review

[← Security documentation](README.md)

**Status:** `KINGDOMS-001` Slice C2 / `K1-P4` implementation candidate  
**Scope:** alliance roster aggregates, snapshot-quality indicators, roster movement, linkage coverage, 7/30-day trends and manager-only individual comparison detail  
**Dependency:** validated Slice C1 snapshot candidate

## Security objective

Slice C2 turns tenant-owned roster and snapshot history into operational summaries without widening the tenant boundary, exposing management-only data, fabricating precision from missing history, or turning observations into punitive member scoring.

## Assets and trust boundaries

Protected assets include:

- alliance aggregate roster metrics;
- individual current power and historical comparison detail;
- application-membership linkage state;
- the fact that a player has stale or missing observations; and
- the historical observations supporting a trend result.

Trust boundaries are:

- authenticated user → active Alliance context;
- aggregate member-visible intelligence → manager-only individual comparison detail;
- historical snapshot coverage → derived trend claims;
- signed-64-bit per-player power → potentially larger alliance totals/deltas; and
- manual/import-quality data → operational interpretation.

## Threats and controls

### Cross-alliance aggregate leakage

**Threat:** A shared Kingdom or Player causes another Alliance's roster/snapshots to enter totals or comparison detail.

**Controls:**

- roster selection is constrained by active `alliance_id`;
- latest-snapshot and trend-baseline queries require the same Alliance plus eligible roster IDs;
- Kingdom/Player identity is never used as authorization; and
- feature coverage includes a same-Kingdom second Alliance whose large power value must not affect the active tenant.

Future aggregate queries must preserve the same tenant-first rule.

### Member access to individual management detail

**Threat:** Ordinary members obtain individual trend/change rows intended for roster management.

**Controls:**

- aggregate dashboard requires `alliance.view`;
- `kingdoms.manage` controls individual comparison detail;
- the controller removes comparison rows from the member response rather than merely hiding them with CSS; and
- manager notes, membership email/IDs and snapshot actor identity are never introduced into this response.

### Missing data represented as zero

**Threat:** Missing snapshots or history are rendered as zero power/change, misleading alliance decisions or unfairly reflecting on a player.

**Controls:**

- no-snapshot players are excluded from recorded-power aggregates and counted explicitly as missing;
- zero remains a valid recorded power value;
- trend is null when no player is comparable;
- individual comparison is null when the baseline contract cannot be satisfied; and
- UI language renders missing/insufficient history separately from numeric zero.

### Sparse history presented as an N-day trend

**Threat:** A recent or very old snapshot is substituted for a 7/30-day baseline, creating a misleading period label.

**Controls:**

- baseline must be at or before the N-day target;
- baseline must also be no older than 2N days;
- observations newer than the target cannot substitute for the baseline;
- arbitrarily old observations are excluded;
- no interpolation is performed; and
- every aggregate trend exposes comparable-player count.

**Residual risk:** Even an eligible baseline may be several days older than the target. The dashboard explains the window rule and manager detail exposes actual capture times so the evidence is inspectable.

### Stale current observations

**Threat:** Old latest power values are presented without warning as current fact.

**Controls:**

- stale/latest values remain included as recorded observations rather than silently disappearing;
- stale/current/missing counts use the explicit 30-day snapshot rule; and
- the dashboard explains that total power is recorded power, not independently verified live game state.

### Overflow or floating-point precision loss

**Threat:** Summing many valid signed-64-bit player powers overflows PHP integers or JavaScript number precision and silently corrupts totals/trends.

**Controls:**

- Slice C2 uses decimal-string arithmetic for totals, averages, medians and signed deltas;
- values remain strings across the Inertia/browser boundary;
- UI formatting does not call JavaScript `Number` on power/delta strings; and
- unit tests include aggregate values larger than signed 64-bit.

### Punitive ranking / unhealthy management behavior

**Threat:** Individual growth/decline data becomes a leaderboard, automated performance score or disciplinary recommendation.

**Controls:**

- ordinary members receive aggregate intelligence only;
- individual comparison detail requires `kingdoms.manage`;
- manager rows are alphabetical, not sorted by growth/decline;
- no winner/loser labels, score, recommendation or threshold action exists;
- power growth is explicitly not a Contribution record; and
- Slice C2 writes no disciplinary or contribution state.

Any future ranking/scoring feature requires separate product/security approval.

### Data-quality side effects

**Threat:** Reading the dashboard mutates snapshots, creates scores, or publishes side effects based on incomplete data.

**Controls:**

- intelligence is read-only and computed synchronously;
- no C2 persistence table/cache is introduced;
- no audit/outbox event is emitted merely for viewing/calculating intelligence; and
- no scheduler/queue workflow is introduced.

### Historical identity/privacy leakage

**Threat:** Aggregate or comparison payloads accidentally include private roster notes, membership contact details or snapshot actor identity.

**Controls:**

- `RosterIntelligence` constructs an explicit narrow projection;
- comparison rows contain roster entry ID/name/state, link boolean, snapshot-quality state, power/capture and trend evidence only; and
- member responses omit comparisons entirely.

## Abuse and interpretation boundary

Roster intelligence describes recorded data quality and change. It does not establish player intent, effort, reliability, contribution, or worth.

The system must not infer:

- that missing data means zero activity;
- that stale data means poor performance;
- that power decline means misconduct;
- that growth deserves contribution credit; or
- that a member should be removed, promoted or punished.

Human managers remain responsible for contextual interpretation.

## Deferred trust boundaries

Outside Slice C2:

- CSV import/export (`K1-P5`);
- public API/webhook intelligence exposure;
- cross-alliance/kingdom-wide analytics;
- transfer/diplomacy workflows;
- bots/OCR/scraping/game APIs; and
- automated recommendations or scoring.

Those require separate input validation, privacy and abuse analysis.

## Verification evidence required

Protected validation should cover:

- exact decimal arithmetic beyond signed-64-bit aggregate range;
- average/median correctness;
- zero-versus-missing behavior;
- stale/current/missing counts;
- recent join/departure semantics;
- linkage coverage;
- irregular 7/30-day baseline-window behavior with no interpolation;
- comparable-player counts;
- member aggregate access with individual comparison rows omitted;
- manager-only comparison detail ordered non-punitively;
- same-Kingdom cross-alliance isolation;
- frontend formatting/type/build checks; and
- inherited staging, backup/restore and image security gates.

Repository checks validate application/repository controls only. Real production cutover remains governed by the existing production launch approval record.
