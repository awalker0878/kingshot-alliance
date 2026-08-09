# KINGDOMS-003 implementation plan

[← Kingdoms alliance intelligence and diplomacy product increment](kingdoms-alliance-intelligence-increment.md)

**Status:** Approved scope — implementation Planned  
**Scope ID:** `KINGDOMS-003`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001` and `KINGDOMS-002` implementations  
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

## 2. Planned phase status

| Phase | Planned outcome | Delivery slice |
| --- | --- | --- |
| `K3-P0` | Identity, tenancy, diplomacy-state, privacy and history contracts locked | Slice A preparation |
| `K3-P1` | Neutral game-side alliance identity and alliance-owned tracking foundation | Slice A |
| `K3-P2` | Append-oriented alliance observations and historical facts | Slice B |
| `K3-P3` | Explicit diplomacy/NAP lifecycle and transition history | Slice C1 |
| `K3-P4` | Manager-private diplomacy contacts | Slice C2 |
| `K3-P5` | Alliance intelligence dashboard and derived descriptive trends | Slice D |
| `K3-P6` | Whole-increment hardening and acceptance | Whole increment |

`KINGDOMS-003` remains Planned until implementation starts and must not be described as current runtime capability before `K3-P6` acceptance.

## 3. `K3-P0` — Design and contract lock

### Objective

Lock the identity, tenancy, state, privacy and history model before runtime schema work begins.

### Required decisions

Lock at minimum:

- neutral game-side alliance entity naming (`KingdomAlliance` or equivalent) and ownership;
- stable game alliance ID normalization/uniqueness rules inside one Kingdom;
- current neutral name/tag semantics and how historical tag/name changes remain observable;
- alliance-owned tracking relation and active/archive lifecycle;
- same-current-Kingdom creation/mutation invariant;
- Alliance-Kingdom-drift recovery/archival behavior;
- diplomacy state vocabulary and allowed transitions;
- effective/review/expiry semantics and no-auto-transition rule;
- observation correction/invalidation semantics without destructive deletion;
- manual-submission retry/idempotency key behavior;
- contact minimum-data/privacy rules;
- member-safe versus manager-private field matrix;
- audit/outbox event families and private-payload exclusions; and
- migration/rollback dependency order.

### Design gates

Before Slice A runtime is accepted, prove that the design cannot:

- confuse a neutral game alliance with a platform tenant;
- grant access because two tenants track the same neutral reference;
- auto-merge by tag/name;
- store one tenant's diplomacy/contact state on a global reference;
- silently retarget records after the platform Alliance changes Kingdom;
- infer diplomacy from observations;
- introduce ranking/threat-score behavior; or
- accidentally expose `kingdoms.*` intelligence/diplomacy events through public webhooks/API.

No future-slice schema or UI placeholders are added in `K3-P0`.

## 4. `K3-P1` / Slice A — External alliance identity and tracking foundation

### Objective

Introduce neutral game-side alliance identity plus an explicit tenant-owned tracking relationship.

### Persistence

Likely entities:

- `KingdomAlliance` — global neutral reference;
- `TrackedKingdomAlliance` (or equivalent) — alliance-owned relationship to the neutral reference.

The neutral reference should support only current reference identity required now:

- ULID;
- `kingdom_id`;
- optional approved stable game alliance ID;
- current name;
- current tag;
- lifecycle state; and
- timestamps.

The tenant-owned tracking record should support:

- active Alliance ID;
- neutral game-side alliance ID;
- captured/current Kingdom context required for fail-closed behavior;
- tracking lifecycle (`active` / `archived` or equivalent);
- manager-only tracking notes if required now; and
- actor/provenance metadata where justified.

### Domain behavior

Add actions to:

- resolve/create a neutral game-side alliance by stable game alliance ID where known;
- explicitly create an unresolved neutral identity when no stable ID exists without name-only deduplication;
- start/stop/archive tenant tracking;
- update neutral current name/tag only through validated identity-aware actions; and
- fail closed if the target alliance is outside the active Alliance's current Kingdom.

Tag/name collision never auto-merges records.

If the platform Alliance Kingdom changes, stale-context tracking remains historical/readable but privileged mutation fails closed until explicit archival/reconciliation.

### Authorization and UI

- ordinary safe tracked-alliance list: `alliance.view`;
- tracking/identity mutation: `kingdoms.manage` + recent password confirmation;
- active-Alliance re-resolution for every submitted tracking/reference ID;
- member list exposes only safe neutral identity/tracking data;
- manager workspace exposes the minimum IDs/notes required to manage tracking.

### Audit/outbox

Material tracking/reference changes produce attributable audit evidence and internal `kingdoms.alliance_intelligence_*` events without private note text.

### Tests and exit criteria

- stable-ID identity resolution tests;
- duplicate tag/name no-auto-merge tests;
- same-Kingdom validation tests;
- cross-tenant tracking/reference-ID tampering tests;
- Alliance-Kingdom-drift tests;
- `alliance.view` / `kingdoms.manage` / password-confirmation tests;
- member payload minimization tests;
- audit/outbox internal-event tests;
- migration rollback/reapply tests; and
- accessibility validation for tracking controls.

Slice A is complete when neutral game-side identity and tenant tracking exist without observations, diplomacy or contacts hidden in the schema.

## 5. `K3-P2` / Slice B — Observations and historical facts

### Objective

Record game-side alliance facts as append-oriented tenant observations and project latest/data-quality state without ranking.

### Persistence

Add an alliance/tracking-scoped observation supporting:

- alliance/tracked-alliance identity;
- observed name/tag;
- optional power;
- optional member count;
- captured time;
- source/provenance (`manual` initially);
- actor where applicable;
- deterministic/manual retry identity; and
- explicit invalidation/correction evidence if an accepted observation must later be marked erroneous.

Do not overwrite historical observations to “fix” the present view.

### Domain behavior

Add a reusable Kingdoms-domain observation action used by first-party manual UI.

It must:

- re-resolve active Alliance/tracking context;
- validate current Kingdom context;
- validate bounded numeric inputs;
- preserve missing values as missing rather than zero;
- make exact request retries idempotent;
- append legitimate later observations;
- update neutral current name/tag only under the locked identity rules; and
- preserve any invalidated observation as history.

The action is a legitimate current-domain contract used by manual UI; a future separately approved `KINGDOMS-004` adapter may reuse it rather than bypassing Kingdoms invariants.

### Query/presentation

Provide:

- latest accepted observation projection;
- current/stale/missing freshness state;
- bounded observation history;
- manager provenance/invalidated detail; and
- member-safe latest/history fields without actor/private management metadata.

### Explicit non-behavior

Do not calculate:

- threat scores;
- combat strength predictions;
- desirability rankings;
- diplomacy recommendations; or
- automatic relationship changes.

### Tests and exit criteria

- append-history tests;
- exact retry/idempotency tests;
- invalidation/correction history-preservation tests;
- missing-vs-zero tests;
- stale/current boundary tests;
- cross-tenant observation tests;
- same-Kingdom drift tests;
- member provenance privacy tests;
- audit/outbox payload-safety tests; and
- realistic observation-history query tests.

Slice B is complete when recorded facts are historically attributable and no score/ranking semantics exist.

## 6. `K3-P3` / Slice C1 — Diplomacy and NAP lifecycle

### Objective

Represent current diplomacy as explicit manager-maintained state with historical transitions and review dates.

### Persistence

Add alliance/tracking-scoped diplomacy state plus append-oriented transition history.

Lock the final state vocabulary in `K3-P0`; expected operational states include concepts such as:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Do not add additional states without a clear workflow requirement.

Current relationship state may store:

- current state;
- effective time;
- optional review/expiry time;
- manager-private terms/rationale only where required; and
- last transition attribution.

Transition history stores prior/new state and actor/effective timestamps without destructive overwrite.

### Domain behavior

- explicit manager transition only;
- validated allowed transitions where needed to preserve workflow meaning;
- repeat of the already-current transition is idempotent;
- expiry/review time does not automatically transition state;
- derived `needs_review` may be calculated from time/state;
- observations, attacks, power changes, transfer state and contact data never auto-transition diplomacy;
- changing/archiving tracking retains relationship history; and
- current-Kingdom drift fails closed for mutations.

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

Optional neutral `KingdomPlayer` linkage may be included only if `K3-P0` proves it is required and can reuse stable player identity rules. A handle/display name alone never links a player automatically.

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

`KINGDOMS-003` remains Planned/In progress/Candidate until the exact final evidence is recorded. Repository/product acceptance does not itself approve real production cutover.

## 10. Pull-request sequencing

Planned dependency order:

1. **Slice A / `K3-P1` — External alliance identity and tracking foundation** (including final `K3-P0` decisions).
2. **Slice B / `K3-P2` — Observations and historical facts**.
3. **Slice C1 / `K3-P3` — Diplomacy and NAP lifecycle**.
4. **Slice C2 / `K3-P4` — Diplomacy contacts**.
5. **Slice D / `K3-P5` — Intelligence dashboard and derived trends**.
6. **`K3-P6` — Whole-increment hardening, audits, documentation and acceptance record**.

Each slice must remain independently migratable/testable and must not add compatibility shims or dormant future-schema fields solely to simplify later slices.

## 11. Suggested branch naming

- `agent/kingdoms-003-slice-a`
- `agent/kingdoms-003-slice-b`
- `agent/kingdoms-003-slice-c1`
- `agent/kingdoms-003-slice-c2`
- `agent/kingdoms-003-slice-d`
- `agent/kingdoms-003-acceptance`

The planning branch may be merged independently before Slice A begins so approved scope remains distinct from implementation evidence.