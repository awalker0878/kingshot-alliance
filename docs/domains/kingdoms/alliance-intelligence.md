# Kingdoms Alliance intelligence and diplomacy

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — Accepted as `KINGDOMS-003`  
**Owning domain:** `Kingdoms`

## 1. Purpose

`KINGDOMS-003` extends Kingdoms with Alliance-owned intelligence and diplomacy workflows for other game-side Alliances:

- neutral game-side Alliance identity and tenant-owned tracking;
- append-oriented factual observation history/corrections;
- explicit manager-maintained diplomacy/NAP state/history;
- minimal manager-private handle-based diplomacy contacts; and
- read-only descriptive Alliance intelligence/trends.

Threat/ranking/scoring, automated recommendations/negotiation, automated game-data ingestion, cross-tenant intelligence sharing, and public Kingdoms API/webhook contracts remain outside the accepted increment.

## 2. Scope and non-scope

In scope:

- `KingdomAlliance` neutral identity;
- `TrackedKingdomAlliance` tenant tracking/current context;
- factual observation history/correction/invalidation;
- explicit diplomacy state/transitions/review metadata;
- manager-private diplomacy contacts;
- descriptive factual summaries/trends/data-quality/review indicators; and
- K3 whole-increment privacy/tenant/query/API-webhook boundaries.

Out of scope:

- player/contact identity linkage;
- phone/address/credential storage;
- cross-tenant/shared intelligence;
- automated ingestion/scraping/OCR/bots;
- threat/desirability/target/combat/punitive scoring;
- automated diplomacy/negotiation/transfer actions; and
- public Kingdoms API/webhook schemas.

## 3. Model and state

### Identity and tenancy

`Alliance` is the platform tenant/authorization principal.

`KingdomAlliance` is global neutral game-side Alliance identity belonging to one `Kingdom`; it is not a User, tenant, membership, role, or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship to a neutral `KingdomAlliance`, captures Kingdom context, and owns manager-private tracking notes.

`KingdomAllianceObservation`, `KingdomAllianceDiplomacy`, `KingdomAllianceDiplomacyTransition`, and `KingdomAllianceDiplomacyContact` are tenant-owned.

Stable `game_alliance_id` scoped to one Kingdom is the only automatic neutral identity key. Names, tags, and handles never auto-merge identity.

### Observations

Accepted observations contain observed name/tag, optional power/member count, capture time, manual source/actor provenance, exact-retry idempotency, correction linkage, invalidation evidence, and current/stale/missing projection.

Invalidated observations remain historical but are excluded from accepted current/trend projections. Future-to-`asOf` observations are excluded from that projection.

Missing values remain missing. Recorded zero remains zero.

### Diplomacy

Vocabulary is exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

State changes only through explicit manager transitions. Review/expiry timestamps are advisory and derive a human-review indicator; they never auto-change state.

### Contacts

Manager-private contacts contain only display name, optional game-side role, approved handle channel, handle, active/inactive lifecycle, optional last-verification time, private notes, and actor/lifecycle provenance.

Contacts do not link to `KingdomPlayer`, User, Membership, role, or permission. Equal names/handles do not prove identity.

### Intelligence projection

`KingdomAllianceIntelligence` is read-only and adds no persistence. It composes accepted tracking/observation/diplomacy/contact facts into member-safe detail plus manager-only aggregate contact diagnostics.

## 4. Invariants

1. Sharing neutral `KingdomAlliance` never shares tenant observations/diplomacy/contacts/notes/intelligence.
2. Stable game Alliance ID within one Kingdom is the only automatic identity key.
3. Observation history is append-oriented; correction appends replacement and invalidates original rather than rewriting it.
4. Missing remains distinct from zero.
5. Diplomacy is explicit human-maintained state and is never inferred from observations/power/member counts/trends/contacts.
6. Review/expiry timestamps never auto-transition diplomacy.
7. Contacts remain manager-private and never become User/Membership/authorization identity.
8. Member intelligence payload excludes contact detail, private terms/rationale, actor provenance, and private notes.
9. Intelligence is descriptive; no threat/target/desirability/composite score exists.
10. Trend baselines are bounded/non-interpolating.
11. Dashboard reads emit no new audit/outbox event.

## 5. Workflows

### Track game-side Alliance

Management establishes `TrackedKingdomAlliance` for a neutral reference in the active Alliance's current Kingdom context. Tracking supports active/archive lifecycle. Kingdom drift preserves authorized historical reads but normal mutations fail closed.

### Record/correct observation

Managers append factual observations. Exact canonical retries return existing state. Correction preserves the original row, appends the corrected observation, and invalidates the original for accepted projection purposes.

### Maintain diplomacy

Managers explicitly transition current diplomacy state and append transition history. Review/expiry timestamps may indicate human review due but do not mutate state.

Private terms/rationale and transition actor history remain manager-private.

### Maintain contacts

Managers add/update/deactivate minimal handle-based contacts. Normal lifecycle deactivates rather than destructively deleting history. Duplicate names/handles remain distinct.

Verification is due when an active contact has never been verified or was last verified more than 30 days before dashboard `asOf`.

### View descriptive intelligence

For active tracked Alliances the projection provides:

- active tracked-Alliance count;
- current/stale/missing observation counts;
- diplomacy-state counts;
- relationship-review-due count; and
- manager-only aggregate contact diagnostics.

Per tracked Alliance, member-safe detail includes:

- neutral name/tag/tracking state;
- Kingdom/context-current indicator;
- latest accepted power/member/capture facts;
- freshness/age;
- immediately-prior factual change;
- bounded 7-day and 30-day changes;
- current explicit diplomacy state and member-safe review/expiry metadata.

Managers additionally receive existing diplomacy/contact workspace links plus aggregate contact counts/verification diagnostics, never contact text.

### Trend selection

At `asOf`:

1. current = latest accepted observation at/before `asOf`;
2. prior = immediately preceding accepted observation;
3. 7-day baseline = closest at/before `asOf - 7d`, not older than `asOf - 14d`;
4. 30-day baseline = closest at/before `asOf - 30d`, not older than `asOf - 60d`;
5. a point newer than target is never substituted;
6. history older than bounded window is ignored;
7. missing endpoint fact makes that metric change missing; and
8. unsupported baseline yields `Insufficient history`.

### Filtering/order

Default is active tracking, all freshness/diplomacy states, name ascending. Filters may select tracking/freshness/diplomacy. Factual sorts may use name, tag, latest power/member count, observation age, or diplomacy state.

Null/missing values sort after supported values. Sorting is navigation, never best/worst/threat/target/desirability priority.

Summary cards describe the complete active tracked population and are not recomputed from detail row filters.

## 6. Authorization, tenancy and privacy

Dashboard requires `alliance.view`. `kingdoms.manage` only determines inclusion of manager-private aggregate contact diagnostics and manager workspace links. No recent-password confirmation is needed for the read-only dashboard.

All K3 mutations use `kingdoms.manage` plus recent password confirmation.

Every projection starts with active `alliance_id`. Historical/archived/drifted tracking may be read when authorized with `contextCurrent=false`; reads never retarget history.

Ordinary members receive no contact diagnostics/IDs/URLs/detail, private terms/rationale, private notes, or actor history.

## 7. Persistence and query semantics

Intelligence itself has no persistent score/table. It batches tenant-first queries for:

- tracked Alliances + bounded neutral/Kingdom/diplomacy relations;
- latest accepted observation;
- prior observation;
- 7-day baseline;
- 30-day baseline; and
- manager-only contact aggregates.

Accepted performance gate models **120 tracked Alliances**, **600 observations**, **120 diplomacy relationships**, and **60 contacts**, with manager projection at or below **10 SELECTs**.

Accepted tenant-first observation/contact indexes support this shape; Slice D required no new migration.

## 8. Events/integrations/background processing

K3 mutations create internal audit/outbox evidence with private text excluded. Representative K3 events remain excluded from wildcard external webhook fan-out.

Dashboard reads create no audit/outbox event. There is no K3 ingestion scheduler/crawler/bot or public API/webhook schema.

## 9. Failure, idempotency and concurrency

- Exact observation retry is idempotent.
- Corrections preserve original history and accepted projection rules.
- Cross-tenant IDs fail closed.
- Kingdom drift blocks normal K3 mutations but preserves authorized historical reads.
- Missing trend support returns insufficient/missing instead of interpolation.
- Contact duplicate names/handles remain distinct; no automatic identity resolution.

## 10. Operations and observability

Operators can distinguish current/stale/missing observations, invalidated corrections, human-review-due diplomacy, context-current versus historical tracking, and manager-only aggregate contact verification state without exposing private contact text.

See [Kingdoms Alliance intelligence operations](operations/kingdoms-alliance-intelligence.md).

## 11. Tests and validation

Whole-increment acceptance proves:

- two platform Alliances may share one stable-ID neutral `KingdomAlliance` without sharing tenant intelligence;
- correction preserves original and drives only accepted facts into projection;
- private tracking/correction/diplomacy/contact strings stay out of member/other-tenant/audit/outbox payloads;
- observation/diplomacy/contact mutations fail closed after Kingdom drift while history remains readable;
- K3 migrations roll back to accepted K2 baseline/reapply in order;
- K3 events remain excluded from wildcard external webhook fan-out; and
- public API contains no K3 contract.

Exact whole-increment validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`.

See the [KINGDOMS-003 exit report](product/kingdoms-alliance-intelligence-exit-report.md), [security review](security/kingdoms-alliance-intelligence-security-review.md), and [accessibility review](product/kingdoms-alliance-intelligence-accessibility.md).

## 12. Related documentation

- [Kingdoms domain](README.md)
- [Roster](roster.md)
- [Transfer planning](transfer-planning.md)
- [KINGDOMS-003 implementation plan](product/kingdoms-alliance-intelligence-implementation-plan.md)
- [KINGDOMS-003 exit report](product/kingdoms-alliance-intelligence-exit-report.md)
