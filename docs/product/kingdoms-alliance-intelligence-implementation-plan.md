# KINGDOMS-003 implementation plan

[← Kingdoms alliance intelligence and diplomacy product increment](kingdoms-alliance-intelligence-increment.md)

**Status:** Approved scope — runtime implementation **In progress**; `K3-P0` Complete; `K3-P1` and `K3-P2` Validated  
**Scope ID:** `KINGDOMS-003`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` and `KINGDOMS-002` implementations  
**K3-P0 decisions:** [KINGDOMS-003 K3-P0 design decisions](kingdoms-alliance-intelligence-p0-decisions.md)  
**Important:** These are implementation phases inside `KINGDOMS-003`; they are not a continuation of historical program phase numbering.

## 1. Purpose

This plan sequences the approved `KINGDOMS-003` Kingdom/alliance intelligence and diplomacy scope into independently reviewable slices while preserving one whole-increment acceptance boundary.

The implementation must preserve the platform rules established by the accepted Kingdoms increments:

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
| `K3-P3` | Planned | Explicit diplomacy/NAP lifecycle and transition history | Slice C1 |
| `K3-P4` | Planned | Manager-private diplomacy contacts | Slice C2 |
| `K3-P5` | Planned | Alliance intelligence dashboard and derived descriptive trends | Slice D |
| `K3-P6` | Planned | Whole-increment hardening and acceptance | Whole increment |

`KINGDOMS-003` runtime implementation is **In progress**. Slice A / `K3-P1` and Slice B / `K3-P2` are validated; diplomacy/NAP, contacts and derived intelligence remain later slices. The whole increment must not be described as Accepted before `K3-P6` passes and its evidence is recorded.

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

No future-slice schema or UI placeholders are added in `K3-P0`.

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

Slice A is complete: neutral game-side identity and tenant tracking exist without observations, diplomacy or contacts hidden in the schema.

### Validation

Exact validated runtime SHA: `f57b81a7550b9a5cb94a2ae233e31da5805c8b55`.

The [Slice A validation record](kingdoms-alliance-intelligence-slice-a-validation.md) records successful Dependency Review, CodeQL and full CI, including frontend checks/build, PostgreSQL migrations, Pint, PHPStan, 314 tests / 3661 assertions, immutable-image staging, backup/restore and image scanning.

`K3-P1` is **Validated**.

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

`K3-P2` is **Validated**. Explicit diplomacy/NAP lifecycle remains `K3-P3`.

## 6. `K3-P3` / Slice C1 — Diplomacy and NAP lifecycle

### Objective

Represent current diplomacy as explicit manager-maintained state with historical transitions and review dates.

### Persistence

Add alliance/tracking-scoped diplomacy state plus append-oriented transition history.

The locked state vocabulary is:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Do not add additional states without an explicit scope update and clear workflow requirement.

Current relationship state may store:

- current state;
- effective time;
- optional review/expiry time;
- manager-private terms/rationale only where required; and
- last transition attribution.

Transition history stores prior/new state and actor/effective timestamps without destructive overwrite.

### Domain behavior

- explicit manager transition only;
- any current state may transition explicitly to any other locked state;
- repeat of the already-current transition with the same effective meaning is idempotent;
- expiry/review time does not automatically transition state;
- derived `needs_review` may be calculated from time/state;
- observations, attacks, power changes, transfer state and contact data never auto-transition diplomacy;
- changing/archiving tracking retains relationship history; and
- current-Kingdom drift fails closed for normal mutations.

### UI

Member-safe current diplomacy label/review indicator may be visible under `alliance.view`.

Manager workspace exposes transition controls, history and private terms/rationale under `kingdoms.manage` plus recent password confirmation.

### Tests and exit criteria

- transition-state tests;
- no-auto-transition-on-expiry tests;
- no-observation-to-diplomacy inference tests;
- private terms/rationale leakage tests;
- transition history/actor tests;
- tenant/object-ID tampering tests;
- Kingdom drift tests;
- idempotency/audit/outbox tests; and
- accessibility validation of state/history controls.

Slice C1 is complete when diplomacy is human-maintained and historically explainable.

## 7. `K3-P4` / Slice C2 — Diplomacy contacts

### Objective

Give authorized managers a minimal private coordination directory without creating identity or authorization shortcuts.

### Persistence

Add alliance/tracking-scoped contacts with only the data required by the initial workflow:

- display name;
- game-side role/title;
- approved contact channel/type;
- handle/identifier;
- active/inactive state;
- last-verified time;
- manager-private notes; and
- actor/provenance where required.

Do not add phone/address/private-secret fields.

`K3-P0` explicitly defers `KingdomPlayer` linkage for diplomacy contacts. A handle/display name never links a player automatically, and Slice C2 does not add a player foreign key merely for future convenience.

### Domain behavior

- create/update/deactivate contact under same Alliance/tracked-alliance context;
- revalidate current Kingdom context for privileged changes;
- contact assignment grants no platform permission;
- contact identity does not create `User` or `AllianceMembership` rows;
- do not treat a contact handle as game-player identity;
- contact deletion should preserve material history where audit/coordination evidence requires it; prefer inactive/archived state to destructive erasure for normal lifecycle.

### Privacy

Initial contact details are manager-private. Ordinary member payloads must not include handles, notes, verification metadata or internal contact IDs.

Structured logs/audit/outbox metadata must not copy private handle/note text.

### Tests and exit criteria

- cross-tenant contact-ID tampering tests;
- manager-only visibility tests;
- contact-does-not-grant-permission regression tests;
- no-user/membership creation regression tests;
- no-name/handle player auto-link tests;
- password-confirmation tests;
- inactive/history preservation tests;
- private payload/log/event safety tests; and
- accessible contact-management controls.

Slice C2 is complete when contacts support diplomacy coordination without becoming authentication/identity/public-directory data.

## 8. `K3-P5` / Slice D — Intelligence dashboard and derived trends

### Objective

Compose tracked identity, observation quality/history and diplomacy state into useful descriptive intelligence without competitive scoring or automated recommendations.

### Derived intelligence

Provide alliance-scoped summaries such as:

- number of active tracked game-side alliances;
- current/stale/missing observation counts;
- latest name/tag/power/member count per tracked alliance;
- prior-observation change;
- bounded 7-day and 30-day power/member change where sufficient history exists;
- current diplomacy-state counts;
- relationships requiring human review because review/expiry time has arrived;
- manager-only contact availability/verification diagnostics; and
- observation age/data-quality indicators.

Missing data remains distinct from zero. Trends use documented bounded historical selection rules and do not interpolate unsupported precision.

### Presentation rules

- default ordering is neutral (for example name/tag);
- user-selected factual sorting may be offered for operational navigation but must not be presented as a “best/worst”, “threat”, “target” or desirability ranking;
- no composite score is calculated;
- no alliance/player punishment recommendation is generated;
- no diplomacy or transfer action is automatically suggested or executed from the metrics.

### Query/index hardening

Use tenant-first indexes and bounded eager/aggregate queries. Validate realistic Kingdom intelligence volume rather than participant-loop queries.

The initial performance gate should model a realistic Kingdom with enough tracked alliances and observation history to expose N+1/unbounded-history regressions; exact volume/budget is locked during implementation from repository query shape.

### UI

Provide:

- member-safe intelligence overview;
- filters for state/freshness/tracking state;
- bounded observation history detail;
- manager diplomacy history/contact detail; and
- explicit data-quality/freshness language.

### Tests and exit criteria

- trend/window/missing-data tests;
- anti-ranking/threat-score architecture tests;
- no-auto-recommendation tests;
- member/manager field-split tests;
- cross-tenant aggregate isolation tests;
- realistic-volume query-count/performance tests;
- accessibility validation of filters/tables/history/status; and
- operations diagnostics review.

Slice D is complete when the accepted observations/diplomacy can be understood operationally without becoming an automated competitive decision engine.

## 9. `K3-P6` — Whole-increment hardening and acceptance

### Objective

Validate the complete `KINGDOMS-003` contract end to end and produce acceptance evidence.

### Required review

- full Kingdoms domain-boundary review including platform `Alliance` versus neutral `KingdomAlliance` identity;
- active-Alliance tenancy/object-ID isolation review across tracking, observations, diplomacy and contacts;
- tag/name collision and stable-ID identity review;
- private notes/terms/contact-handle review;
- abuse review confirming no threat ranking, punitive scoring or automated diplomacy recommendation;
- accessibility review of tracking, observations/history, diplomacy, contacts and intelligence surfaces;
- migration rollback/reapply validation from the accepted `KINGDOMS-002` baseline;
- realistic-volume query/index review;
- observation idempotency/history integrity review;
- Alliance-Kingdom drift/reconciliation review;
- operations/observability review;
- API/webhook review confirming intelligence/diplomacy events remain internal;
- current capability matrix and Kingdoms product/domain index updates from Planned to Implemented only after acceptance; and
- dedicated `KINGDOMS-003` exit report with exact validated SHA/protected-check evidence.

### Acceptance gate

The complete stack must pass the repository's protected quality/security pipeline, including:

- frontend quality/build;
- PHP quality/static analysis/tests;
- PostgreSQL migrations;
- dependency/security analysis;
- CodeQL;
- immutable-image build;
- staging validation;
- backup/restore; and
- image scanning where those controls remain part of the repository gate.

`KINGDOMS-003` remains In progress/Candidate until the exact final whole-increment evidence is recorded. Repository/product acceptance does not itself approve real production cutover.

## 10. Pull-request sequencing

Dependency order:

1. **`K3-P0` — Design/security contract lock** (documentation-only prerequisite).
2. **Slice A / `K3-P1` — External alliance identity and tracking foundation** — Validated.
3. **Slice B / `K3-P2` — Observations and historical facts** — Validated.
4. **Slice C1 / `K3-P3` — Diplomacy and NAP lifecycle**.
5. **Slice C2 / `K3-P4` — Diplomacy contacts**.
6. **Slice D / `K3-P5` — Intelligence dashboard and derived trends**.
7. **`K3-P6` — Whole-increment hardening, audits, documentation and acceptance record**.

Each runtime slice must remain independently migratable/testable and must not add compatibility shims or dormant future-schema fields solely to simplify later slices.

## 11. Suggested branch naming

- `agent/kingdoms-003-p0`
- `agent/kingdoms-003-slice-a`
- `agent/kingdoms-003-slice-b`
- `agent/kingdoms-003-slice-c1`
- `agent/kingdoms-003-slice-c2`
- `agent/kingdoms-003-slice-d`
- `agent/kingdoms-003-acceptance`

The planning branch may be merged independently before Slice A begins so approved scope remains distinct from implementation evidence. The P0 branch may be stacked on planning and establishes the final contract that Slice A inherited.
