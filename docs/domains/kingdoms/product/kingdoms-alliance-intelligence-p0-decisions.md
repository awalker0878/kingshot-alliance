# KINGDOMS-003 K3-P0 design decisions

[← KINGDOMS-003 implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)

**Scope ID:** `KINGDOMS-003`  
**Gate:** `K3-P0` — identity, tenancy, diplomacy-state, privacy/history contract lock  
**Status:** **Complete — implementation contract locked**  
**Runtime impact:** None. This record authorizes later sliced implementation only.

## 1. Purpose

`K3-P0` locks the business and security contract for Kingdom/alliance intelligence before any runtime schema, routes, UI, jobs or API surfaces are introduced.

The decisions below are normative for `K3-P1` through `K3-P6`. A later slice must not silently weaken them. If implementation evidence shows a decision is unworkable, update this record explicitly in the same review before changing runtime behavior.

## 2. Identity model

### 2.1 Neutral game-side alliance entity

The canonical neutral game-side alliance reference is named `KingdomAlliance`.

`KingdomAlliance` belongs to one `Kingdom` and is **not** the platform `Alliance` tenant aggregate. It grants no authentication, membership, role, permission or cross-tenant access.

A `KingdomAlliance` may exist even when no corresponding platform Alliance exists. `KINGDOMS-003` does not add a platform-Alliance↔KingdomAlliance ownership/linkage model.

### 2.2 Stable game alliance identifier

An approved stable game-side alliance identifier, when known, is the only automatic identity-resolution key.

Rules:

- uniqueness is scoped to one `Kingdom`;
- the normalized value is compared exactly inside that Kingdom;
- the same stable ID in two different Kingdoms does not imply one identity;
- stable-ID resolution may reuse the matching neutral `KingdomAlliance`;
- a conflicting stable ID fails closed rather than relinking an existing record silently.

### 2.3 Name and tag are not identity keys

Alliance name and tag are current neutral display/reference fields only.

Name/tag collisions never auto-merge, auto-link, deduplicate or retarget a `KingdomAlliance`.

When no stable game alliance ID is known, an authorized manager may deliberately create a distinct unresolved neutral reference. Later resolution to a stable ID must be explicit and must fail closed if that stable ID already belongs to a different neutral reference.

### 2.4 Neutral current identity versus tenant history

The neutral reference may store only the current neutral identity needed for present display: current name, current tag, stable ID, Kingdom and lifecycle.

Tenant observation history is not stored on the global reference. Historical names/tags observed by one platform Alliance remain tenant-owned observation facts.

No diplomacy state, contact, manager note, observation provenance or derived intelligence is stored on `KingdomAlliance`.

## 3. Tenant-owned tracking model

The canonical alliance-owned tracking relationship is named `TrackedKingdomAlliance`.

It belongs to exactly one platform `Alliance`, references one neutral `KingdomAlliance`, and captures the Kingdom context under which tracking was created.

Locked invariants:

- active-Alliance context is authoritative;
- the neutral reference must belong to the active Alliance's current Kingdom when tracking is created;
- one active tracking relationship per platform Alliance + neutral KingdomAlliance is sufficient for the initial increment;
- tracking lifecycle is `active` or `archived`;
- normal historical preservation uses archive rather than destructive deletion;
- manager tracking notes, if persisted, are tenant-owned and manager-private;
- another tenant tracking the same neutral `KingdomAlliance` does not expose or combine tenant data.

## 4. Alliance-Kingdom drift

A `TrackedKingdomAlliance` retains its captured Kingdom context.

If the platform Alliance later changes Kingdom:

- historical tracking/intelligence remains readable under the normal tenant visibility rules;
- observation, diplomacy and contact mutations against the stale tracking context fail closed;
- creating new tracking requires a neutral alliance in the platform Alliance's current Kingdom;
- stale tracking may be archived as the explicit recovery action;
- archival is allowed even after Kingdom drift because it reduces active mutable state;
- the application never rewrites captured Kingdom context or silently retargets the neutral reference.

If leadership wants to track an alliance in the new Kingdom, it creates a deliberate new tracking relationship under the current Kingdom.

## 5. Diplomacy state contract

### 5.1 State vocabulary

The initial diplomacy vocabulary is exactly:

- `unknown`
- `neutral`
- `friendly`
- `nap`
- `ally`
- `rival`

No score, severity, priority or threat level is implied by these values.

### 5.2 Transition semantics

Diplomacy is explicit human-maintained workflow state.

Any current diplomacy state may transition explicitly to any other state because real diplomacy can change directly and the product must not invent unsupported intermediate states.

Rules:

- a manager explicitly selects the new state;
- repeating the already-current state with the same effective meaning is an idempotent no-op;
- every material state change creates append-oriented transition history;
- transition history records previous/new state, effective time and actor attribution;
- relationship history is retained when tracking is archived;
- observations, attacks, roster changes, power trends, transfer plans, contacts and dates never auto-transition diplomacy.

### 5.3 Effective/review/expiry time

A diplomacy relationship may carry:

- `effective_at` — when the current relationship is considered effective;
- optional `review_at` — when leadership wants to review it; and
- optional `expires_at` — a planning/term date when applicable.

Dates are advisory workflow metadata only.

Reaching `review_at` or `expires_at` may derive a `needs_review` presentation flag but **never mutates diplomacy state automatically**. For example, an expired NAP remains recorded as `nap` until an authorized manager explicitly changes it.

## 6. Observation history and correction

### 6.1 Observation facts

Alliance intelligence observations are tenant-owned append-oriented facts associated with one `TrackedKingdomAlliance`.

The initial manual observation contract may record:

- observed name/tag;
- optional power;
- optional member count;
- captured time;
- source/provenance; and
- actor attribution where applicable.

Missing values remain missing and are never coerced to zero.

### 6.2 Exact retry idempotency

Exact manual-submission retries must not multiply one accepted observation.

The implementation should derive a deterministic observation fingerprint from the tenant/tracking identity, capture time, source and normalized factual payload. The exact storage/index form is a Slice B implementation detail, but retry semantics are locked here.

A genuinely later observation, even with identical values, remains a new observation because its capture time differs.

No `KINGDOMS-004` source-event or adapter fields are reserved in K3 solely for future ingestion.

### 6.3 Correction/invalidation

Accepted observations are never destructively overwritten or deleted as routine correction.

If an observation is erroneous:

- the original observation remains historical evidence;
- an authorized manager may invalidate it with attributable time/actor and bounded reason metadata;
- the latest/current projection excludes invalidated observations;
- a corrected factual value is recorded as a new observation, optionally referencing the invalidated observation for explainability;
- invalidation does not rewrite other tenants' observations or the neutral reference history.

Private correction rationale must not be copied into ordinary member payloads or outbox metadata.

## 7. Diplomacy contacts

### 7.1 Initial data model

Contacts are tenant-owned coordination records under one `TrackedKingdomAlliance`.

The initial increment may store only:

- display name;
- game-side role/title;
- channel type;
- handle/identifier;
- active/inactive state;
- last-verified time; and
- manager-private notes.

Allowed initial channel semantics are handle-based coordination such as in-game, Discord or another explicitly labelled handle/channel. The product does not solicit phone numbers, home addresses, private credentials, recovery material or unrelated personal data.

### 7.2 Contacts are not identity or authorization

`KINGDOMS-003` does **not** link diplomacy contacts to `Player` in the initial increment.

A display name or handle therefore cannot become an automatic player-identity bridge.

Creating or assigning a contact:

- does not create a `User`;
- does not create an `AllianceMembership`;
- does not grant `kingdoms.manage` or any other permission;
- does not authenticate the person; and
- does not expose another tenant's contact directory.

Normal lifecycle uses inactive/archive semantics rather than routine destructive deletion where history matters.

## 8. Authorization and privacy matrix

### 8.1 Authorization

The accepted Kingdoms boundary is reused unchanged:

- ordinary safe reads: `alliance.view`;
- tracking, observation, diplomacy and contact mutations: `kingdoms.manage`;
- privileged mutations: `kingdoms.manage` **plus recent password confirmation**;
- Alliance→Kingdom association remains `alliance.manage`;
- no diplomacy-specific role or permission is introduced;
- controller role-name checks remain prohibited;
- platform administrators do not implicitly become tenant diplomacy managers.

Every submitted tracking, neutral-alliance, observation, diplomacy-transition or contact identifier must be re-resolved beneath the active Alliance and applicable tracking relationship before tenant-owned data is returned or mutated.

### 8.2 Ordinary member-safe data

`alliance.view` may expose only approved safe coordination data such as:

- current neutral alliance name/tag;
- safe tracking state;
- latest accepted power/member-count facts when present;
- freshness/current-stale-missing indicators;
- current diplomacy state label; and
- derived `needs_review` indicator.

Ordinary member payloads must not expose:

- manager tracking notes;
- private diplomacy terms/rationale;
- contact handle/identifier;
- contact notes or verification metadata;
- observation actor or manager-only provenance;
- invalidation reasons/actors;
- diplomacy transition actors;
- internal IDs that are not required by the member workflow; or
- another tenant's observations/history even when the same neutral reference is shared.

### 8.3 Manager-private data

After `kingdoms.manage` authorization, management views may expose only the additional fields required for tracking, observation review/correction, diplomacy history/terms and contact coordination.

Private text must be excluded from structured logs unless a separately reviewed diagnostic requirement proves otherwise.

## 9. Audit and internal outbox contract

Material privileged K3 changes create attributable audit evidence and durable internal outbox messages in the same business transaction where required by the existing repository pattern.

Locked event families include concepts equivalent to:

- `kingdoms.alliance_tracking_created`
- `kingdoms.alliance_tracking_archived`
- `kingdoms.alliance_observation_recorded`
- `kingdoms.alliance_observation_invalidated`
- `kingdoms.diplomacy_changed`
- `kingdoms.diplomacy_contact_saved`
- `kingdoms.diplomacy_contact_deactivated`

Exact event names may be normalized during implementation, but all remain under `kingdoms.*` and therefore **internal-only** under the existing Integrations exclusion.

Audit/outbox metadata may include safe scoped IDs, states, timestamps and bounded factual values where required. It must not include private diplomacy terms, manager notes, contact handles, contact notes or private correction rationale.

K3 introduces no public Kingdoms API scope, route, webhook schema or external event catalog entry.

## 10. Migration dependency order

The implementation must remain independently reversible by slice.

Planned dependency order is:

1. `KingdomAlliance` neutral references;
2. `TrackedKingdomAlliance` tenant tracking;
3. alliance observation/history persistence;
4. diplomacy current relationship + transition history;
5. diplomacy contacts.

Rollback reverses that order.

Slice A must not create observation, diplomacy, contact or dashboard placeholder columns. Later slices own their own migrations.

## 11. Query and history principles

- neutral reference lookup may be global only for neutral fields;
- tenant intelligence queries always begin from explicit Alliance ownership;
- history is bounded/paginated or otherwise query-limited where lists can grow;
- latest/freshness projections must avoid N+1 access patterns;
- stale/missing/current semantics are derived from recorded observations, not invented defaults;
- 7/30-day trends remain descriptive facts and do not become rankings or recommendations.

## 12. Explicit non-capabilities

`K3-P0` and the eventual K3 runtime do not approve or reserve hidden placeholders for:

- automated game-data ingestion (`KINGDOMS-004`);
- scraping, OCR, bots or undocumented/unapproved game APIs;
- opt-in shared/cross-alliance kingdom intelligence (`KINGDOMS-005`);
- public alliance/contact directories;
- public Kingdoms API or webhook contracts;
- threat/desirability/risk scores;
- punitive or automated alliance rankings;
- battle-outcome prediction;
- automated diplomacy recommendations or transitions;
- automatic transfer destination/readiness/completion decisions;
- diplomacy-driven contribution scoring; or
- AI-generated enforcement/player-management decisions.

## 13. P0 exit decision

`K3-P0` is complete when this record, the implementation plan and the P0 security review agree on the above contract.

Completion of P0 authorizes Slice A implementation work. It does **not** claim that any `KINGDOMS-003` runtime capability exists yet, and it does not approve production deployment.