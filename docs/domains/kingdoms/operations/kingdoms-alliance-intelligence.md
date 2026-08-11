# Kingdoms alliance intelligence operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** **Accepted** through `K3-P6` whole-increment hardening

## Runtime ownership

Slices A through D are synchronous first-party web workflows using PostgreSQL and existing Kingdoms domain services. Slice D adds a read-only descriptive projection only; K3-P6 adds acceptance tests/evidence and no new product mutation path.

There is no Kingdoms scheduler, crawler, scraper, OCR process, bot, diplomacy timer, automated negotiation process, automated game-data ingestion process, scoring worker or recommendation engine.

Routes:

- member-safe tracked alliance list: `/alliance/kingdom-alliances`;
- manager tracking workspace: `/alliance/kingdom-alliances/manage`;
- member/manager descriptive intelligence: `/alliance/kingdom-alliances/intelligence`;
- member/manager observation history: `/alliance/kingdom-alliances/{tracking}/history`;
- manager diplomacy workspace: `/alliance/kingdom-alliances/{tracking}/diplomacy`;
- manager contact workspace: `/alliance/kingdom-alliances/{tracking}/diplomacy/contacts`;
- password-confirmed observation mutations under `/alliance/kingdom-alliances/{tracking}/observations`;
- password-confirmed diplomacy transitions under `/alliance/kingdom-alliances/{tracking}/diplomacy/transitions`; and
- password-confirmed contact create/update/deactivate under `/alliance/kingdom-alliances/{tracking}/diplomacy/contacts`.

The intelligence route is read-only and requires `alliance.view`. It does not use recent-password confirmation because it cannot mutate state.

## Expected durable state

Operators may diagnose K3 state through:

- `kingdom_alliances` neutral identity rows;
- `tracked_kingdom_alliances` tenant tracking rows;
- `kingdom_alliance_observations` tenant factual history;
- `kingdom_alliance_diplomacy_relationships` tenant current relationship state;
- `kingdom_alliance_diplomacy_transitions` append-oriented relationship history;
- `kingdom_alliance_diplomacy_contacts` tenant manager-private coordination contacts;
- `audit_events`; and
- `outbox_messages`.

Slice D creates no new table, cache, materialized view, audit event or outbox event. Dashboard values are derived on read from accepted tenant state.

## Intelligence projection semantics

At dashboard `asOf` time:

- current observation = latest accepted observation captured at or before `asOf`;
- invalidated observations are excluded;
- observations after `asOf` are excluded;
- prior comparison = immediately preceding accepted observation;
- 7-day baseline = closest accepted observation at or before `asOf - 7 days`, no older than `asOf - 14 days`;
- 30-day baseline = closest accepted observation at or before `asOf - 30 days`, no older than `asOf - 60 days`;
- unsupported history is not interpolated/extrapolated;
- missing power/member values remain missing; recorded zero remains zero; and
- current/stale/missing freshness uses the existing 30-day Kingdoms threshold.

For power/member trend endpoints, a missing value for one metric makes that metric's change missing without discarding another supported metric from the same observation pair.

## Headline summaries and filters

Headline summaries describe the complete **active tracked-alliance** population:

- active tracked count;
- observation current/stale/missing counts;
- diplomacy-state counts; and
- relationships whose review or expiry date has arrived.

For managers, headline diagnostics additionally include:

- active tracked alliances with at least one active contact; and
- active tracked alliances with at least one active contact requiring verification.

Contact verification is due when `last_verified_at` is null or older than 30 days at `asOf`.

Row filters do **not** redefine these headline counts. Filters narrow the table only. Supported row filters are tracking state, freshness and diplomacy state.

Default detail order is active tracking, name ascending. Factual sorting may use name, tag, latest power, latest member count, observation age or diplomacy state. Treat this as navigation only; do not describe it in operations/support material as ranking, threat order, priority targets, best/worst alliances or recommendations.

## Privacy diagnostics

Member-safe intelligence may include:

- neutral alliance name/tag;
- tracking/current-Kingdom context;
- latest accepted power/member/capture time;
- freshness/age;
- factual prior/7-day/30-day changes;
- explicit diplomacy state; and
- advisory review/expiry timing.

Member payloads do not include:

- manager tracking notes;
- observation actor/private correction/invalidation text;
- diplomacy terms/rationale or actor attribution;
- contact IDs, names, roles, channels, handles, notes or verification diagnostics; or
- manager workspace URLs.

Manager intelligence may include contact **counts**, verification-due count and latest active verification time, plus links to the existing private workspaces. It still does not copy contact display name, role, channel, handle or manager notes into the dashboard payload.

Do not copy private contact/diplomacy text into logs, metrics labels, support tickets, audit metadata or outbox messages while diagnosing the dashboard.

## Query and performance diagnostics

The dashboard query shape is intentionally batched:

1. tenant tracking query with bounded eager loads for neutral reference, Kingdom and current diplomacy;
2. latest accepted observation projection;
3. prior accepted observation projection;
4. 7-day baseline projection;
5. 30-day baseline projection; and
6. manager-only contact aggregate projection.

The correlated observation projections select at most one accepted row per tracked alliance for each semantic point. They do not load each alliance's unbounded history into PHP.

The accepted realistic-volume gate uses:

- 120 tracked alliances;
- 600 accepted observations (five per tracking record);
- 120 diplomacy relationships; and
- 60 active contacts.

The manager projection completes with **no more than 10 SELECT statements**. An increased query count that scales with tracked alliance count is an N+1 regression and should block future changes.

Existing observation indexes are tenant/tracking-first and cover capture/acceptance selection. Existing contact indexes cover tenant/tracking/state and tenant verification-time diagnostics. Slice D intentionally adds no migration.

## Failure modes and recovery

### Missing history

`Insufficient history` is a valid result. Do not backfill a fabricated trend, carry forward a newer point, or widen the bounded window manually just to populate the dashboard.

### Missing value versus zero

A null power/member value means the observation did not support that fact. Zero is a real recorded value. Do not normalize null to zero in SQL, PHP, exports or UI.

### Stale observation

Stale means the latest accepted observation is older than 30 days. Record a new factual observation through the normal manager workflow if current data becomes available. Do not modify capture timestamps to make history appear fresh.

### Review due

A relationship is review-due when review or expiry time has arrived. This is advisory only. A manager must explicitly decide whether any diplomacy transition is appropriate; never update state directly because the dashboard flags review.

### Contact verification due

Verification due means an active contact is unverified or older than the 30-day verification threshold. Managers may update contact verification through the contact workflow after actual verification. Do not infer that the contact is invalid or deactivate it automatically.

### Alliance Kingdom changed

Historical rows remain readable and show historical Kingdom context. The dashboard does not retarget tracking/observations/diplomacy/contacts after drift. Observation, diplomacy and contact mutations fail closed; archival remains the explicit stale-tracking recovery action.

### Cross-tenant discrepancy

All aggregate queries are Alliance-scoped. If two platform Alliances track the same neutral `KingdomAlliance`, their derived metrics can legitimately differ because observations/diplomacy/contacts are tenant-owned. Do not reconcile them by copying another tenant's rows.

## Audit and outbox

Dashboard reads emit no new business events. Existing mutation events remain internal `kingdoms.*` events and stay excluded from generic external webhook fan-out, including wildcard subscriptions.

K3-P6 includes a representative wildcard-webhook regression across tracking, observation, diplomacy and contact event families and verifies zero external deliveries.

If audit/outbox records appear during a dashboard-only request, investigate the underlying request path; Slice D itself should perform no mutation.

## Migration and rollback

Accepted K3 migrations are:

1. `2026_08_09_140000_create_kingdom_alliance_tracking.php`;
2. `2026_08_09_150000_create_kingdom_alliance_observations.php`;
3. `2026_08_10_090000_create_kingdom_alliance_diplomacy.php`;
4. `2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php`.

K3-P6 proves a K3-only rollback in reverse order to the accepted `KINGDOMS-002` baseline while K2 roster/snapshot/transfer state remains intact, followed by successful forward reapplication. No compatibility shim/dormant future schema is retained.

## Acceptance evidence

Exact whole-increment validated implementation SHA:

`068c4086744f71d33453734f1f1b05fe1430cbff`

Protected Dependency Review `31430279647`, CodeQL `31430279652`, and CI `31430279638` passed. The CI gate includes 483 Pint files, PHPStan/Larastan 345/345 with zero errors, 359 tests / 4,824 assertions, frontend quality/build, PostgreSQL migrations, immutable image, ephemeral staging, backup/restore, vulnerability scan and cleanup.

See the [KINGDOMS-003 exit report](../product/kingdoms-alliance-intelligence-exit-report.md).

## Stop conditions

Escalate instead of applying manual fixes when recovery would require:

- widening trend windows or fabricating/interpolating missing history;
- converting missing values to zero;
- using trends to calculate threat/desirability/composite scores;
- treating factual sorting as target/best/worst ranking;
- generating automated diplomacy, attack, punishment or transfer recommendations;
- changing diplomacy automatically because review/expiry or trend data changed;
- exposing private contact/diplomacy text to members/logs/outbox;
- aggregating another tenant's observations because the neutral reference is shared;
- rewriting captured Kingdom context after drift;
- adding a public intelligence API/webhook without separate approval; or
- adding a scheduler/ingestion/bot path to keep the dashboard populated.

`KINGDOMS-003` is Accepted for repository/product purposes; real production cutover remains separately not approved.
