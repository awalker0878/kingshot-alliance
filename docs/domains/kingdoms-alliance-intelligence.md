# Kingdoms alliance intelligence and diplomacy

[← Domain documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice C2 / `K3-P4` candidate — tracking, observations, explicit diplomacy lifecycle, and manager-private diplomacy contacts

## Purpose

`KINGDOMS-003` extends the Kingdoms domain with alliance-owned intelligence and diplomacy workflows for other game-side alliances.

- Slice A / `K3-P1` established neutral game-side alliance identity and tenant-owned tracking.
- Slice B / `K3-P2` added validated append-oriented factual observation history.
- Slice C1 / `K3-P3` added explicit manager-maintained diplomacy state and append-oriented transition history.
- Slice C2 / `K3-P4` adds a minimal manager-private handle-based diplomacy contact directory.

Threat/ranking/scoring, automated recommendations, automated negotiation, automated game-data ingestion, cross-tenant intelligence sharing and public Kingdoms API/webhook contracts remain outside the current runtime slice.

## Identity and tenancy

`Alliance` is the platform tenant and authorization principal.

`KingdomAlliance` is a global neutral game-side alliance reference belonging to one `Kingdom`. It is not a tenant, user, membership, role or permission principal.

`TrackedKingdomAlliance` is the tenant-owned relationship between one platform Alliance and one neutral `KingdomAlliance`. It captures Kingdom context and owns manager-private tracking notes.

`KingdomAllianceObservation`, `KingdomAllianceDiplomacy`, `KingdomAllianceDiplomacyTransition`, and `KingdomAllianceDiplomacyContact` are tenant-owned. Sharing a neutral `KingdomAlliance` does not share observations, diplomacy state/history, private terms/rationale, contacts, handles, notes or actor provenance.

The only automatic neutral alliance identity key remains an approved stable `game_alliance_id` scoped to one Kingdom. Name/tag never auto-merge identity.

Diplomacy contact display names and handles are coordination labels only. They are not identity keys and never create, merge, or link a `KingdomPlayer`, platform `User`, or `AllianceMembership`.

## Observation contract

Slice B remains authoritative for factual observations:

- observed name/tag;
- optional power/member count;
- capture time;
- manual source and actor provenance;
- deterministic exact-retry idempotency;
- correction by append plus original invalidation; and
- current/stale/missing projection using the accepted 30-day threshold.

Observations are facts only. They never infer or automatically change diplomacy state.

## Diplomacy state vocabulary

The Slice C1 vocabulary remains fixed to exactly:

- `unknown`;
- `neutral`;
- `friendly`;
- `nap`;
- `ally`; and
- `rival`.

Slice C2 adds no diplomacy state.

The member-safe default is `unknown` when no explicit relationship row exists. A manager may explicitly record any of the six states, including `unknown` when they need to record review metadata or private rationale without asserting another relationship state.

## Current relationship and transition history

`kingdom_alliance_diplomacy_relationships` stores one current relationship per Alliance + tracked alliance. It contains:

- current state;
- effective time;
- optional review time;
- optional expiry time;
- manager-private terms;
- manager-private rationale; and
- last transition actor attribution.

`kingdom_alliance_diplomacy_transitions` is append-oriented history. Each material change snapshots prior/new state, dates, terms/rationale, actor and recorded time.

Historical transition rows are not edited when the current relationship changes.

## Explicit-only transitions and idempotency

Diplomacy changes only through the explicit manager transition action.

Any current state may be explicitly changed to any other locked state. There is no inferred transition matrix based on power, attacks, transfer state, observations, dates, or contacts.

An exact repeat of the current state plus the same effective/review/expiry dates and normalized terms/rationale is idempotent. A same-state request with changed metadata is material and appends history.

Review and expiry times remain advisory planning metadata only. Reaching either date never mutates current state.

## Diplomacy contact contract

`kingdom_alliance_diplomacy_contacts` stores manager-private coordination contacts under one Alliance + tracked game-side alliance + neutral reference.

Allowed contact data is intentionally minimal:

- display name;
- optional game-side role/title;
- approved handle-based channel type;
- handle/identifier;
- active/inactive state;
- optional last-verified time;
- manager-private notes;
- creation/update actor provenance; and
- deactivation actor/time provenance.

Approved channel values are:

- `in_game`;
- `discord`; and
- `other_handle` for another explicitly labelled handle/channel.

The schema intentionally has no:

- `kingdom_player_id`;
- membership/role/permission link;
- phone number or home-address field;
- credential/password/recovery-secret field;
- name/handle uniqueness constraint; or
- future scoring/ranking/recommendation field.

Duplicate display names and handles are allowed because equality is not proof of identity.

## Contact lifecycle and idempotency

Contacts use an explicit active/inactive lifecycle.

- new contacts start active;
- active contacts may be edited;
- an exact active-contact update retry with identical normalized business fields is a no-op;
- deactivation marks the record inactive and captures actor/time;
- repeated deactivation is idempotent; and
- inactive contacts remain manager-readable history and are not edited or destructively deleted.

If coordination resumes after deactivation, a manager creates a new active contact instead of rewriting the preserved inactive record.

Contact creation intentionally does not deduplicate two separate new submissions by name or handle; doing so would turn weak contact labels into identity keys.

## Contact non-behavior

Creating, editing, verifying, or deactivating contacts never:

- changes diplomacy state;
- infers NAP/ally/rival status;
- changes review/expiry metadata;
- creates or links `KingdomPlayer` identity;
- creates a platform account or membership;
- grants `kingdoms.manage` or any other permission;
- changes transfer destination/readiness/completion;
- calculates threat/desirability/combat/ranking scores;
- recommends diplomacy action; or
- sends automated negotiation messages.

## Read and privacy surfaces

The ordinary tracked-alliance list remains `alliance.view` and exposes only member-safe diplomacy data:

- current diplomacy label; and
- review-due indicator.

Ordinary members do not receive relationship/transition IDs, manager workflow dates, actor attribution, private terms/rationale, or any contact data.

The diplomacy workspace and diplomacy contact workspace require `kingdoms.manage`.

The contact workspace may expose to managers only the coordination fields and lifecycle provenance needed to manage contacts. It is bounded to the latest **250** ordered records for one tracked alliance.

The first-party contact UI explicitly tells managers not to store phone numbers, home addresses, passwords, recovery material or unrelated private secrets.

## Kingdom drift and tracking lifecycle

Tracking captures `kingdom_id` when created. If the platform Alliance later changes Kingdom:

- tracking, observation, diplomacy and contact history remain readable to authorized users;
- normal diplomacy/contact mutation fails closed;
- historical rows are never silently retargeted; and
- Slice A archival remains the stale-context recovery action.

Archiving tracking also preserves diplomacy/contact history and makes new diplomacy/contact mutation fail closed.

## Authorization

- safe tracked-alliance list/history reads: `alliance.view`;
- diplomacy/contact workspace read: `kingdoms.manage`;
- diplomacy transition: `kingdoms.manage` + recent password confirmation;
- contact create/update/deactivate: `kingdoms.manage` + recent password confirmation;
- submitted tracking/contact IDs are re-resolved under the active Alliance;
- neutral reference and captured Kingdom context are revalidated under row locks before mutation; and
- no role-name, coordinator, or contact-derived permission shortcut is introduced.

## Audit and outbox

Material diplomacy events remain internal:

- `kingdoms.diplomacy_transitioned`;
- `kingdoms.diplomacy_contact_saved`; and
- `kingdoms.diplomacy_contact_deactivated`.

Private relationship terms/rationale and private contact display/role/channel/handle/note text are excluded from audit/outbox metadata. Contact event metadata is limited to bounded IDs, lifecycle state/times, verification time and create/update classification.

Existing Integration rules keep all `kingdoms.*` events out of generic external webhook fan-out.

## Persistence and indexes

Current K3 tables are:

- `kingdom_alliances`;
- `tracked_kingdom_alliances`;
- `kingdom_alliance_observations`;
- `kingdom_alliance_diplomacy_relationships`;
- `kingdom_alliance_diplomacy_transitions`; and
- `kingdom_alliance_diplomacy_contacts`.

Contact indexes are tenant-first for tracked-alliance/state listing and last-verification lookup. No contact uniqueness constraint implies identity.

Slice C2 adds no scoring/ranking/recommendation, ingestion, scraping/OCR/bot, public API, webhook schema, automated negotiation, or automatic transfer fields.

## Explicit non-behavior

Through Slice C2, K3 does not:

- infer relationship state from observations, contacts, attacks, power, member count or transfer state;
- auto-transition on review/expiry;
- rank alliances or calculate threat/desirability scores;
- predict combat outcomes;
- recommend diplomacy actions;
- create/send negotiation messages;
- expose contacts to ordinary members or other tenants;
- treat contact names/handles as player/account identity;
- ingest game data automatically; or
- expose K3 data through public API/webhooks.

## Deferred slices

- `K3-P5` — descriptive intelligence views/trends;
- `K3-P6` — whole-increment hardening and acceptance.

No later-slice schema or runtime behavior is introduced by `K3-P4`.
