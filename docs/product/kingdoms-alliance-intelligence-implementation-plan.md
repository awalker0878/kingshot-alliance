# KINGDOMS-003 implementation plan

[← Kingdoms alliance intelligence and diplomacy product increment](kingdoms-alliance-intelligence-increment.md)

**Status:** Approved scope — implementation **Accepted**; `K3-P0` Complete; `K3-P1`–`K3-P5` Validated; `K3-P6` Accepted  
**Scope ID:** `KINGDOMS-003`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` and `KINGDOMS-002` implementations  
**K3-P0 decisions:** [KINGDOMS-003 K3-P0 design decisions](kingdoms-alliance-intelligence-p0-decisions.md)  
**Acceptance:** [KINGDOMS-003 exit report](kingdoms-alliance-intelligence-exit-report.md)  
**Important:** These are implementation phases inside `KINGDOMS-003`; they are not a continuation of historical program phase numbering.

## 1. Purpose

This plan sequences the approved `KINGDOMS-003` Kingdom/alliance intelligence and diplomacy scope into independently reviewable slices while preserving one whole-increment acceptance boundary.

The implementation preserves the platform rules established by the accepted Kingdoms increments:

- domain-first runtime ownership under `app/Domain/<Domain>`;
- explicit active-Alliance tenancy for tenant-owned observations/workflows;
- global neutral reference identity only where genuinely shared;
- platform `Alliance` identity remains distinct from game-side alliance identity;
- stable external identifiers are the only automatic identity-match keys;
- display names/tags/handles never auto-merge identity;
- `alliance.view` for ordinary safe reads and `kingdoms.manage` for Kingdoms intelligence/diplomacy mutations;
- policy/permission authorization rather than controller role-name checks;
- recent password confirmation for privileged mutations;
- thin controllers with business behavior in actions/services/queries;
- transactional persistence and row locking where invariants/concurrency require it;
- attributable audit evidence for privileged changes;
- transactional outbox for durable internal side effects;
- append-oriented observation/relationship history rather than destructive overwrite;
- member-safe versus manager-private presentation boundaries;
- no compatibility shims after migrations are complete;
- code/tests authoritative for exact runtime behavior;
- security, accessibility, operations and living documentation updated with each slice; and
- no dormant ingestion, public-sharing, ranking, threat-score, AI-recommendation or public-API placeholders.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K3-P0` | **Complete** | Identity, tenancy, diplomacy-state, privacy and history contracts locked | Pre-runtime contract gate |
| `K3-P1` | **Validated** | Neutral game-side alliance identity and alliance-owned tracking foundation | Slice A |
| `K3-P2` | **Validated** | Append-oriented alliance observations and historical facts | Slice B |
| `K3-P3` | **Validated** | Explicit diplomacy/NAP lifecycle and transition history | Slice C1 |
| `K3-P4` | **Validated** | Manager-private diplomacy contacts | Slice C2 |
| `K3-P5` | **Validated** | Alliance intelligence dashboard and derived descriptive trends | Slice D |
| `K3-P6` | **Accepted** | Whole-increment hardening, security/accessibility/rollback/query/integration review and acceptance evidence | Whole increment |

`KINGDOMS-003` is **Accepted** for repository/product purposes. Exact whole-increment validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`. Real production cutover remains a separate approval decision.

## 3. `K3-P0` — Design and contract lock — Complete

### Objective

Lock the identity, tenancy, state, privacy and history model before runtime schema work begins.

### Locked decisions

The normative decisions are recorded in [KINGDOMS-003 K3-P0 design decisions](kingdoms-alliance-intelligence-p0-decisions.md) and the companion [K3-P0 security/privacy review](../security/kingdoms-alliance-intelligence-p0-security-review.md).

They lock at minimum:

- canonical neutral game-side alliance entity `KingdomAlliance` and tenant-owned `TrackedKingdomAlliance` tracking relationship;
- stable game alliance ID scoped to one Kingdom as the only automatic identity-resolution key;
- name/tag/handle values as non-identity display/coordination data that never auto-merge;
- global neutral current identity separated from tenant-owned observation/diplomacy/contact history;
- tracking lifecycle `active` / `archived` and one active tracking relationship per tenant/reference;
- same-current-Kingdom creation/mutation invariant and fail-closed Kingdom drift behavior with archival recovery;
- diplomacy states exactly `unknown`, `neutral`, `friendly`, `nap`, `ally`, `rival`;
- explicit human-maintained transitions between any diplomacy states with append-oriented transition history;
- effective/review/expiry dates as advisory workflow metadata that never auto-transition state;
- append-oriented observations with deterministic exact-retry idempotency;
- invalidation/correction preserving original observations rather than destructive overwrite;
- no K3 diplomacy-contact linkage to `KingdomPlayer` in the initial increment;
- contact data minimization and manager-private handle/note visibility;
- `alliance.view` safe reads and `kingdoms.manage` + recent password confirmation for all K3 mutations;
- member-safe versus manager-private field boundaries;
- internal-only `kingdoms.*` audit/outbox event families with private-text exclusions; and
- migration order `KingdomAlliance` → tracking → observations → diplomacy/history → contacts, reversed for rollback.

### Design gates

The locked design cannot:

- confuse a neutral game alliance with a platform tenant;
- grant access because two tenants track the same neutral reference;
- auto-merge by tag/name/handle;
- store one tenant's diplomacy/contact state on a global reference;
- silently retarget records after the platform Alliance changes Kingdom;
- infer diplomacy from observations;
- introduce ranking/threat-score behavior; or
- accidentally expose `kingdoms.*` intelligence/diplomacy events through public webhooks/API.

No future-slice schema or UI placeholders were added in `K3-P0`.

## 4. `K3-P1` / Slice A — External alliance identity and tracking foundation — Validated

### Objective

Introduce neutral game-side alliance identity plus an explicit tenant-owned tracking relationship.

### Persistence

Entities:

- `KingdomAlliance` — global neutral reference;
- `TrackedKingdomAlliance` — alliance-owned relationship to the neutral reference.

The neutral reference supports only current reference identity required now:

- ULID;
- `kingdom_id`;
- optional approved stable game alliance ID;
- current name;
- current tag;
- lifecycle state; and
- timestamps.

The tenant-owned tracking record supports:

- active Alliance ID;
- neutral game-side alliance ID;
- captured Kingdom context required for fail-closed behavior;
- tracking lifecycle (`active` / `archived`);
- manager-only tracking notes; and
- archive/timestamp evidence.

### Domain behavior

Delivered actions:

- resolve/create a neutral game-side alliance by stable game alliance ID where known;
- explicitly create an unresolved neutral identity when no stable ID exists without name-only deduplication;
- start/archive tenant tracking;
- update neutral current name/tag only through validated identity-aware actions; and
- fail closed if the target alliance is outside the active Alliance's current Kingdom.

Tag/name collision never auto-merges records. Stable IDs are assign-once and same-Kingdom conflicts fail closed instead of merging or relinking references.

If the platform Alliance Kingdom changes, stale-context tracking remains historical/readable but normal privileged mutation fails closed. Archival remains available as the safe terminal recovery action; tracking is never silently retargeted.

### Authorization and UI

- ordinary safe tracked-alliance list: `alliance.view`;
- tracking/identity mutation: `kingdoms.manage` + recent password confirmation;
- active-Alliance re-resolution for every submitted tracking/reference ID;
- member list exposes only safe neutral identity/tracking data; and
- manager workspace exposes only the IDs/stable identity/private notes required to manage tracking.

### Audit/outbox

Material tracking/reference changes produce attributable audit evidence and internal `kingdoms.alliance_intelligence_*` events without private note text.

### Tests and exit criteria

Validated coverage includes:

- stable-ID identity resolution/reuse/conflict tests;
- duplicate tag/name no-auto-merge tests;
- same-Kingdom and Kingdom-drift validation;
- cross-tenant tracking/reference-ID tampering tests;
- `alliance.view` / `kingdoms.manage` / password-confirmation tests;
- member payload minimization tests;
- private-note audit/outbox payload-safety tests;
- archive idempotency/re-tracking history tests;
- complete Kingdoms migration rollback/reapply tests; and
- accessibility/public-API/future-slice architecture guards.

Exact validated runtime SHA: `f57b81a7550b9a5cb94a2ae233e31da5805c8b55`.

The [Slice A validation record](kingdoms-alliance-intelligence-slice-a-validation.md) records successful Dependency Review, CodeQL and full CI, including frontend checks/build, PostgreSQL migrations, Pint, PHPStan, 314 tests / 3661 assertions, immutable-image staging, backup/restore and image scanning.

## 5. `K3-P2` / Slice B — Observations and historical facts — Validated

### Objective

Record game-side alliance facts as append-oriented tenant observations and project latest/data-quality state without ranking.

### Persistence

`KingdomAllianceObservation` is an alliance-owned record scoped to the tracked and neutral game-side alliance reference. It stores:

- Alliance/tracked-alliance/neutral-reference identity;
- observed name/tag;
- optional power;
- optional member count;
- capture time;
- manual source and actor provenance;
- deterministic SHA-256 idempotency key;
- optional correction link;
- invalidation time/actor; and
- manager-private invalidation/correction reason.

The observation migration uses explicit bounded PostgreSQL index/FK names and adds the self-referencing correction foreign key after table creation. It adds no diplomacy/contact/scoring/ingestion/public-integration placeholders.

### Domain behavior

Delivered actions:

- record a manual observation under the active Alliance/tracking context;
- validate current Kingdom context and fail closed after Alliance-Kingdom drift;
- preserve missing values as missing rather than zero;
- bound power to the signed 64-bit range and member count to the first-party validation range;
- reject capture time more than five minutes in the future;
- make exact request retries idempotent;
- append legitimate later observations;
- correct an accepted observation by appending a replacement and invalidating the original in one transaction;
- invalidate an accepted observation without deleting or rewriting its historical facts;
- keep repeated invalidation idempotent; and
- reproject neutral current name/tag from the latest accepted neutral-reference observation.

An older observation inserted later cannot overwrite a newer accepted neutral identity because projection orders by `captured_at`, then observation ULID.

### Query/presentation

Delivered projections provide:

- latest accepted observation;
- current/stale/missing freshness using the accepted 30-day Kingdoms threshold;
- a latest-observation projection on the tracked-alliance list;
- bounded observation history capped at 250 rows;
- manager provenance/correction/invalidation detail; and
- member-safe factual latest/history fields without actor/private management metadata.

Invalidated rows are excluded from member latest/freshness projections and remain manager-visible history. Power is serialized to browsers as a decimal string to prevent JavaScript integer precision loss. The first-party `datetime-local` value is converted to ISO/UTC before submission.

### Authorization/privacy

- safe list/history reads: `alliance.view`;
- record/correct/invalidate: `kingdoms.manage` + recent password confirmation;
- tracking and observation IDs are re-resolved under the active Alliance;
- cross-tenant substitution fails closed;
- private correction/invalidation reason text remains manager-only and is excluded from audit/outbox metadata; and
- Alliance-Kingdom drift preserves historical reads while blocking observation mutation.

### Audit/outbox

Material observation changes produce attributable internal events:

- `kingdoms.alliance_intelligence_observation_recorded`;
- `kingdoms.alliance_intelligence_observation_corrected`; and
- `kingdoms.alliance_intelligence_observation_invalidated`.

Exact retries and repeated invalidation do not create duplicate durability evidence. Existing Integrations policy keeps all `kingdoms.*` events out of generic external webhook fan-out.

### Explicit non-behavior

Slice B does not calculate or infer:

- threat scores;
- combat strength predictions;
- desirability rankings;
- diplomacy/NAP state or recommendations;
- automatic relationship changes;
- game-data ingestion/scraping/OCR/bot behavior; or
- cross-tenant/public intelligence sharing.

### Tests and validation

Validated coverage includes:

- append history and capture-time latest selection;
- exact retry/idempotency and event de-duplication;
- correction/invalidation history preservation and idempotency;
- missing-vs-zero semantics;
- numeric/future-capture bounds;
- cross-tenant object-ID isolation;
- same-Kingdom drift behavior;
- password-confirmation enforcement;
- member/manager provenance privacy split;
- private-reason audit/outbox payload safety;
- migration rollback/reapply;
- accessibility validation; and
- no-future-slice/public-API architecture guards.

Exact validated runtime SHA: `bf064075971ce0f81bd800b5ce0c5c88c9c1010c`.

The [Slice B validation record](kingdoms-alliance-intelligence-slice-b-validation.md) records successful Dependency Review `31342802384`, CodeQL `31342802370` and CI `31342802361`, including frontend quality/build, PostgreSQL migrations, Pint 459 files, PHPStan 329 files / 0 errors, 323 tests / 3851 assertions, immutable-image staging, backup/restore and image scanning.

## 6. `K3-P3` / Slice C1 — Diplomacy and NAP lifecycle — Validated

### Objective

Represent current diplomacy as explicit manager-maintained state with historical transitions and review dates.

### Persistence

Alliance/tracking-scoped diplomacy state plus append-oriented transition history is implemented.

The locked state vocabulary is:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Current relationship state stores current state, effective time, optional review/expiry time, manager-private terms/rationale where supplied, and last transition attribution. Transition history preserves state/metadata snapshots without destructive overwrite.

### Domain behavior

- explicit manager transition only;
- any current state may transition explicitly to any other locked state;
- repeat of the already-current transition with the same normalized meaning is idempotent;
- same-state material metadata change appends history;
- expiry/review time does not automatically transition state;
- derived `needs_review` is calculated at read time;
- observations, power changes, transfer state and contact data never auto-transition diplomacy; and
- current-Kingdom drift fails closed for normal mutations while retaining history.

### Authorization/privacy

Member-safe current diplomacy label/review indicator is visible under `alliance.view`. The manager workspace exposes transitions/history/private terms/rationale under `kingdoms.manage`; mutations require recent password confirmation.

Private terms/rationale are excluded from audit/outbox metadata and the event remains internal-only.

### Validation

Slice C1 validation is recorded in [KINGDOMS-003 Slice C1 validation](kingdoms-alliance-intelligence-slice-c1-validation.md). It passed transition, expiry/no-auto-transition, privacy, history, tenancy, drift, idempotency, accessibility, static-analysis and protected CI gates.

## 7. `K3-P4` / Slice C2 — Diplomacy contacts — Validated

### Objective

Give authorized managers a minimal private coordination directory without creating identity or authorization shortcuts.

### Persistence

Alliance/tracking-scoped contacts contain only the data required by the approved workflow:

- display name;
- optional game-side role/title;
- approved handle-based channel/type;
- handle/identifier;
- active/inactive state;
- last-verified time;
- manager-private notes; and
- actor/lifecycle provenance.

No phone/address/private-secret fields or player/user/membership/role/permission foreign keys exist.

### Domain behavior

- create/update/deactivate contact under the same Alliance/tracked-alliance context;
- revalidate current Kingdom context for privileged changes;
- exact identical active saves are idempotent;
- inactive contacts remain historical/readable;
- contact assignment grants no platform permission;
- contact identity does not create `User` or `AllianceMembership` rows;
- contact handle is never treated as game-player identity; and
- normal lifecycle deactivates rather than destructively deletes.

### Privacy

Contact details are manager-private. Ordinary member payloads do not include handles, notes, verification metadata or internal contact IDs. Audit/outbox metadata does not copy private contact text.

### Validation

Exact validated runtime SHA: `c8b414d9023d837913fdc46908c55e109d59b386`.

The [Slice C2 validation record](kingdoms-alliance-intelligence-slice-c2-validation.md) records successful tenancy, non-identity/non-authorization, privacy, lifecycle, password, accessibility and protected pipeline gates.

## 8. `K3-P5` / Slice D — Intelligence dashboard and derived trends — Validated

### Objective

Compose tracked identity, observation quality/history and diplomacy state into useful descriptive intelligence without competitive scoring or automated recommendations.

### Derived intelligence

The accepted read-only dashboard provides:

- number of active tracked game-side alliances;
- current/stale/missing observation counts;
- latest name/tag/power/member count per tracked alliance;
- immediately prior accepted-observation change;
- bounded 7-day power/member change using a 7–14-day baseline window;
- bounded 30-day power/member change using a 30–60-day baseline window;
- current diplomacy-state counts;
- relationships requiring human review because review/expiry time has arrived;
- manager-only aggregate contact availability/verification diagnostics; and
- observation age/data-quality indicators.

Missing data remains distinct from zero. Trends do not interpolate unsupported precision.

### Presentation rules

- default ordering is neutral name order;
- user-selected factual sorting is operational navigation and is not presented as a best/worst, threat, target or desirability ranking;
- no composite score is calculated;
- no alliance/player punishment recommendation is generated; and
- no diplomacy or transfer action is automatically suggested or executed from the metrics.

### Query/index hardening

The realistic-volume gate models 120 tracked alliances, 600 observations, 120 diplomacy relationships and 60 contacts. The manager projection is capped at **10 SELECT statements**.

### Validation

Exact validated runtime SHA: `a9d2e22ea1c710bc72f4dc8824a70e15dda04e75`.

The [Slice D validation record](kingdoms-alliance-intelligence-slice-d-validation.md) records successful Dependency Review, CodeQL, frontend, migrations, Pint 481 files, PHPStan 345/345 with zero errors, 353 tests / 4,452 assertions, immutable image, staging, backup/restore and image scanning.

## 9. `K3-P6` — Whole-increment hardening and acceptance — Accepted

### Objective

Validate the complete `KINGDOMS-003` contract end to end and produce acceptance evidence.

### Completed review

Whole-increment hardening validates:

- platform `Alliance` versus neutral `KingdomAlliance` identity boundaries;
- active-Alliance tenancy/object-ID isolation across tracking, observations, diplomacy and contacts;
- stable-ID identity and name/tag collision behavior;
- private tracking/correction/diplomacy/contact data boundaries;
- absence of threat ranking, punitive scoring and automated diplomacy recommendations;
- accessibility across tracking, observations/history, diplomacy, contacts and intelligence;
- K3-only migration rollback/reapply to the accepted `KINGDOMS-002` baseline;
- realistic-volume query/index behavior;
- observation correction/idempotency/history integrity;
- Alliance-Kingdom drift/read-only history/archive recovery;
- operations/observability contracts;
- internal-only K3 event behavior even for wildcard webhook subscriptions; and
- no public K3 API contract.

A dedicated end-to-end acceptance workflow spans neutral identity/tracking → observations/correction → diplomacy → contacts → member/manager dashboard → tenant isolation → private payload safety → Kingdom drift → archival recovery.

### Acceptance evidence

Exact validated implementation SHA:

`068c4086744f71d33453734f1f1b05fe1430cbff`

Protected checks:

- Dependency Review `31430279647` — success;
- CodeQL `31430279652` — success;
- CI `31430279638` — success;
- Pint — 483 files;
- PHPStan/Larastan — 345/345, zero errors;
- ParaTest/PHPUnit — 359 tests / 4,824 assertions;
- frontend quality/type/build — success;
- PostgreSQL migrations — success;
- immutable production image — success;
- ephemeral staging — success;
- backup/restore — success;
- image scan — success; and
- cleanup — success.

See the [KINGDOMS-003 exit report](kingdoms-alliance-intelligence-exit-report.md), [whole-increment security review](../security/kingdoms-alliance-intelligence-security-review.md), and [accessibility review](kingdoms-alliance-intelligence-accessibility.md).

`KINGDOMS-003` is **Accepted** for repository/product purposes. Repository/product acceptance does not itself approve real production cutover.

## 10. Pull-request sequencing

Completed dependency order:

1. **`K3-P0` — Design/security contract lock** — Complete.
2. **Slice A / `K3-P1` — External alliance identity and tracking foundation** — Validated.
3. **Slice B / `K3-P2` — Observations and historical facts** — Validated.
4. **Slice C1 / `K3-P3` — Diplomacy and NAP lifecycle** — Validated.
5. **Slice C2 / `K3-P4` — Diplomacy contacts** — Validated.
6. **Slice D / `K3-P5` — Intelligence dashboard and derived trends** — Validated.
7. **`K3-P6` — Whole-increment hardening, audits, documentation and acceptance record** — Accepted.

Each runtime slice remains independently migratable/testable and the accepted increment adds no compatibility shims or dormant future-schema fields solely for deferred capabilities.

## 11. Branch naming used

- `agent/kingdoms-003-p0`
- `agent/kingdoms-003-slice-a`
- `agent/kingdoms-003-slice-b`
- `agent/kingdoms-003-slice-c1`
- `agent/kingdoms-003-slice-c2`
- `agent/kingdoms-003-slice-d`
- `agent/kingdoms-003-acceptance`

Historical planning/P0 branches remain implementation evidence. The accepted whole-increment product record is the K3 exit report and exact validated implementation SHA above.
