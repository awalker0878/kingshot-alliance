# Kingdoms alliance intelligence and diplomacy

[← Domain documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** **Accepted** — tracking, observations, explicit diplomacy, manager-private contacts, descriptive alliance intelligence, and whole-increment hardening

## Purpose

`KINGDOMS-003` extends the Kingdoms domain with alliance-owned intelligence and diplomacy workflows for other game-side alliances.

- Slice A / `K3-P1` established neutral game-side alliance identity and tenant-owned tracking.
- Slice B / `K3-P2` added append-oriented factual observation history.
- Slice C1 / `K3-P3` added explicit manager-maintained diplomacy state and transition history.
- Slice C2 / `K3-P4` added a minimal manager-private handle-based diplomacy contact directory.
- Slice D / `K3-P5` composes those accepted facts into read-only descriptive summaries and bounded trends.
- `K3-P6` validated the complete tenant/privacy/history/rollback/query/accessibility/API-webhook contract and accepted the increment.

Threat/ranking/scoring, automated recommendations, automated negotiation, automated game-data ingestion, cross-tenant intelligence sharing and public Kingdoms API/webhook contracts remain outside the accepted runtime increment.

## Identity and tenancy

`Alliance` is the platform tenant and authorization principal.

`KingdomAlliance` is a global neutral game-side alliance reference belonging to one `Kingdom`. It is not a tenant, user, membership, role or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship between one platform Alliance and one neutral `KingdomAlliance`. It captures Kingdom context and owns manager-private tracking notes.

`KingdomAllianceObservation`, `KingdomAllianceDiplomacy`, `KingdomAllianceDiplomacyTransition`, and `KingdomAllianceDiplomacyContact` are tenant-owned. Sharing a neutral `KingdomAlliance` never shares tenant observations, diplomacy, contacts, terms, notes, actor provenance, or derived tenant intelligence.

The only automatic neutral alliance identity key remains an approved stable `game_alliance_id` scoped to one Kingdom. Names, tags and contact handles never auto-merge identity.

## Observation contract

Accepted observations remain factual, append-oriented history containing:

- observed name/tag;
- optional power/member count;
- capture time;
- manual source and actor provenance;
- deterministic exact-retry idempotency;
- correction by append plus original invalidation; and
- current/stale/missing projection using the accepted 30-day threshold.

Invalidated observations remain historical but are excluded from intelligence projections. Observations captured after the dashboard `asOf` time are also excluded from that projection.

Missing values remain missing. A recorded power/member value of zero remains zero and is never treated as missing.

## Diplomacy contract

The diplomacy vocabulary remains exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Diplomacy changes only through explicit manager transitions. Review and expiry timestamps are advisory; reaching either timestamp creates only a derived human-review indicator and never changes state automatically.

Private terms, rationale, transition actors and internal relationship IDs remain outside member intelligence payloads.

## Diplomacy contacts

Contacts remain manager-private coordination records with only display name, optional game-side role, approved handle channel, handle, active/inactive lifecycle, last-verification time, private notes, and actor/lifecycle provenance.

Contacts have no `KingdomPlayer`, user, membership, role or permission link. Equal names or handles do not prove identity and never trigger automatic merge/link behavior.

The intelligence dashboard exposes only aggregate contact diagnostics to managers:

- count of active contacts per tracked alliance;
- count of active contacts whose verification is due; and
- latest active-contact verification timestamp.

Verification is due when an active contact has never been verified or its last verification is older than 30 days at the dashboard `asOf` time. The dashboard never includes contact display names, roles, channels, handles or manager notes.

Ordinary members receive no contact diagnostics, contact IDs, contact URLs or contact detail.

## Descriptive intelligence contract

`KingdomAllianceIntelligence` is a read-only projection. It adds no persistence and emits no audit/outbox event because it performs no business mutation.

For the active tracked-alliance population it provides:

- active tracked-alliance count;
- current/stale/missing observation counts;
- diplomacy-state counts;
- relationship-review-due count; and
- manager-only aggregate contact diagnostics.

For each displayed tracked alliance it provides member-safe factual detail:

- current neutral name/tag and tracking state;
- Kingdom and current/historical context indicator;
- latest accepted power/member count and capture time;
- observation freshness and age;
- immediately-prior accepted-observation change;
- bounded 7-day power/member change;
- bounded 30-day power/member change; and
- current explicit diplomacy state plus review/expiry metadata safe for the member view.

Managers additionally receive links to the existing diplomacy/contact workspaces and aggregate contact diagnostics. Private terms, rationale, contact text and actor provenance are not copied into the intelligence payload.

## Trend selection rules

Trend selection is deterministic and non-interpolating.

At an `asOf` time:

1. **Current** is the latest accepted observation captured at or before `asOf`.
2. **Prior change** compares current with the immediately preceding accepted observation.
3. **7-day baseline** is the closest accepted observation at or before `asOf - 7 days`, but no older than `asOf - 14 days`.
4. **30-day baseline** is the closest accepted observation at or before `asOf - 30 days`, but no older than `asOf - 60 days`.
5. A point newer than the target is never substituted as the baseline.
6. History older than the bounded window is ignored for that trend.
7. If either endpoint lacks a factual power/member value, that metric's change is missing rather than estimated.
8. If no supported baseline exists, the trend is `Insufficient history`; no interpolation or extrapolation occurs.

These rules deliberately match the existing Kingdoms roster-intelligence baseline convention.

## Filtering and ordering

The default dashboard view is:

- active tracking only;
- all freshness states;
- all diplomacy states;
- name ascending.

Filters may select tracking state, observation freshness and diplomacy state.

Users may factually sort by name, tag, latest power, latest member count, observation age or diplomacy state. Sorting is operational navigation only. It must never be labelled or interpreted as best/worst, threat, target, desirability or strategic priority.

Null/missing sort values remain distinct and sort after supported values. The dashboard calculates no composite score.

Summary cards describe the complete active tracked population and are not recomputed from the row filters; filters only narrow the detail rows. This prevents a filtered detail view from silently changing the meaning of the headline operational counts.

## Authorization and privacy

The dashboard requires `alliance.view` under the active Alliance context.

`kingdoms.manage` is used only to decide whether manager-private aggregate contact diagnostics and manager workspace links are included. No recent-password confirmation is required because the dashboard is read-only and creates no privileged mutation path.

Every projection query begins with the active Alliance ID before tracking/observation/contact selection. Sharing a global neutral `KingdomAlliance` never grants access to another tenant's observations or derived intelligence.

Historical/archived or Kingdom-drifted tracking may be read when selected, with `contextCurrent=false`; the dashboard never retargets or repairs historical context.

## Query and performance contract

The dashboard uses batched tenant-first queries rather than one query per tracked alliance:

- tracked alliances plus bounded eager-loaded neutral/Kingdom/diplomacy relations;
- one latest accepted-observation projection query;
- one prior accepted-observation projection query;
- one 7-day baseline query;
- one 30-day baseline query; and
- one manager-only contact aggregate query.

The accepted performance gate models 120 tracked game-side alliances, 600 accepted observations, 120 diplomacy relationships and 60 contacts. The manager projection remains at or below 10 SELECT statements.

No Slice D migration is required; accepted tenant-first observation/contact indexes support the projection shape.

## Whole-increment acceptance contract

`K3-P6` additionally proves:

- two platform Alliances may share a neutral stable-ID `KingdomAlliance` without sharing tenant-owned intelligence;
- a complete observation correction preserves the original and drives only accepted facts into the dashboard;
- private tracking/correction/diplomacy/contact strings stay out of member/other-tenant payloads and K3 audit/outbox payloads;
- observation, diplomacy and contact mutations fail closed after Alliance-Kingdom drift while authorized history remains readable;
- K3 migrations roll back cleanly to the accepted `KINGDOMS-002` baseline and reapply in dependency order;
- representative K3 events remain excluded from wildcard external webhook fanout; and
- the public API contains no K3 Kingdom-alliance/diplomacy contract.

Exact whole-increment validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`.

## Explicit non-behavior

Accepted K3 does not:

- infer diplomacy from power, members, observations, trends or contacts;
- auto-transition diplomacy on review/expiry;
- calculate threat, desirability, target, combat or composite scores;
- rank alliances as best/worst or strategic targets;
- recommend diplomacy, punishment, attack or transfer actions;
- execute diplomacy or transfer actions;
- create/send negotiation messages;
- expose manager-private contact text or diplomacy terms/rationale;
- ingest/scrape/OCR game data;
- create a public Kingdoms API or webhook schema; or
- emit new `kingdoms.*` events for dashboard reads.

## Acceptance status

`KINGDOMS-003` is **Accepted** for repository/product purposes. See the [exit report](../product/kingdoms-alliance-intelligence-exit-report.md), [whole-increment security review](../security/kingdoms-alliance-intelligence-security-review.md), and [accessibility review](../product/kingdoms-alliance-intelligence-accessibility.md).

Real production cutover remains separately **not yet approved**.
