# Kingdoms alliance intelligence operations

[← Operations documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice C2 / `K3-P4` candidate

## Runtime ownership

Slices A through C2 are synchronous first-party web workflows using PostgreSQL, the existing audit recorder and transactional outbox. They introduce no Kingdoms scheduler, queue worker, crawler, scraper, OCR process, bot, diplomacy timer, automated negotiation process or automated game-data ingestion process.

Routes:

- member-safe tracked alliance list: `/alliance/kingdom-alliances`;
- manager tracking workspace: `/alliance/kingdom-alliances/manage`;
- member/manager observation history: `/alliance/kingdom-alliances/{tracking}/history`;
- manager diplomacy workspace: `/alliance/kingdom-alliances/{tracking}/diplomacy`;
- manager contact workspace: `/alliance/kingdom-alliances/{tracking}/diplomacy/contacts`;
- password-confirmed observation mutations under `/alliance/kingdom-alliances/{tracking}/observations`;
- password-confirmed diplomacy transitions under `/alliance/kingdom-alliances/{tracking}/diplomacy/transitions`; and
- password-confirmed contact create/update/deactivate under `/alliance/kingdom-alliances/{tracking}/diplomacy/contacts`.

There is intentionally no contact delete route.

## Expected durable state

Operators may diagnose current K3 state through:

- `kingdom_alliances` neutral identity rows;
- `tracked_kingdom_alliances` tenant tracking rows;
- `kingdom_alliance_observations` tenant factual history;
- `kingdom_alliance_diplomacy_relationships` tenant current relationship state;
- `kingdom_alliance_diplomacy_transitions` append-oriented relationship history;
- `kingdom_alliance_diplomacy_contacts` tenant manager-private coordination contacts;
- `audit_events`; and
- `outbox_messages`.

## Observation behavior

Observation history remains append-oriented. Exact retry uses deterministic SHA-256 identity, latest accepted projection uses greatest capture time then observation ULID, and invalidated rows remain historical while being excluded from member/latest projection.

Do not directly update/delete observation facts to repair history; use the accepted correction/invalidation actions.

## Diplomacy transitions

Diplomacy is explicit human-maintained state. Valid states remain:

`unknown`, `neutral`, `friendly`, `nap`, `ally`, and `rival`.

A transition request updates the current relationship and appends a transition snapshot in one transaction. Exact current-meaning retries are idempotent; same-state metadata changes are material and intentionally append history.

Review and expiry are advisory only. There is no scheduled state mutation.

## Diplomacy contacts

Contacts are manager-private coordination records, not player/account identity.

Allowed channels are handle-based:

- `in_game`;
- `discord`; and
- `other_handle`.

Normal lifecycle:

1. create an active contact;
2. update the active contact when coordination metadata changes;
3. optionally update factual last-verification time;
4. deactivate when the contact is no longer current; and
5. preserve inactive rows as manager-readable history.

An exact active-contact update retry is a no-op. Repeated deactivation is also a no-op.

Do not deduplicate new contacts solely because display names or handles match. Those values are not identity keys and duplicate values may legitimately represent separate records.

Inactive contacts are not edited. If coordination later resumes, create a new active contact rather than rewriting historical inactive data.

## Contact data-handling rules

The contact UI and operational contract allow handles only. Do not store:

- phone numbers;
- home addresses;
- passwords or credentials;
- recovery material/secrets; or
- unrelated private personal data.

Do not manually add `KingdomPlayer`, membership, user, role, or permission linkage to a contact row. That would violate the K3 identity and authorization boundary.

## Failure modes and recovery

### Alliance has no current Kingdom

Tracking creation fails. Configure the Alliance Kingdom through the accepted Alliance-setting workflow before retrying.

### Stable ID conflict

Assigning a stable game alliance ID that already belongs to another neutral reference in the same Kingdom fails closed. Do not repair this by rewriting IDs directly in PostgreSQL.

### Alliance Kingdom changed

Historical tracking, observation, diplomacy and contact history remain readable, but normal tracking/observation/diplomacy/contact mutations fail because captured Kingdom context no longer equals the Alliance current Kingdom.

Archive stale tracking if appropriate, then deliberately establish tracking under the new current Kingdom. Do not rewrite captured Kingdom or historical foreign keys to make old history appear current.

### Archived tracking

Diplomacy/contact history remains manager-readable. New transitions and contact mutations are rejected. Do not reactivate history by editing the archived row directly.

### Invalid diplomacy dates

Review and expiry cannot precede effective time, and review cannot be later than expiry when both are supplied.

### Future contact verification time

`last_verified_at` cannot be in the future. Correct the factual verification time and retry rather than bypassing validation.

### Duplicate contact values

Two new records may intentionally share the same display name or handle. Do not merge/delete one solely because those labels match.

### Inactive contact edit attempted

Inactive rows are preserved history and reject edit. Create a new active record if the coordination relationship resumes.

### Outbox publication failure

The business transaction remains committed with an unpublished outbox row. Recover through the existing `outbox:publish` workflow; do not recreate the business mutation solely to publish its event.

## Privacy and diagnostics

Manager tracking notes, observation correction/invalidation reasons, diplomacy terms/rationale, and all diplomacy contact detail are private tenant data.

Do not copy contact display names, roles, channels, handles or manager notes into logs, tickets, metrics labels, audit metadata or outbox payloads.

Structured contact diagnostics should use bounded identifiers/state only, such as Alliance ID, tracked record ID, neutral reference ID, contact ID, active/inactive state, lifecycle timestamps, verification timestamp and event type.

Member-facing tracked-alliance payloads deliberately expose no contact data or internal contact IDs.

## Audit/outbox expectations

Material diplomacy/contact changes emit only internal Kingdoms evidence:

- `kingdoms.diplomacy_transitioned`;
- `kingdoms.diplomacy_contact_saved`;
- `kingdoms.diplomacy_contact_deactivated`.

Contact event payloads exclude display name, role, channel, handle and notes. Existing Integration policy excludes all `kingdoms.*` events from generic external webhook fan-out.

## Migration/rollback

Current K3 migrations:

1. `2026_08_09_140000_create_kingdom_alliance_tracking.php`;
2. `2026_08_09_150000_create_kingdom_alliance_observations.php`;
3. `2026_08_10_090000_create_kingdom_alliance_diplomacy.php`;
4. `2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php`.

Rollback order is the reverse: contacts first, diplomacy transitions/current relationship second, observations third, then tracking/neutral references, followed by the accepted K2/K1 chain.

The C2 migration has no compatibility shim and no player/membership/permission-link, scoring/ranking/recommendation, ingestion or public-integration placeholders.

## Stop conditions

Escalate instead of applying manual data fixes when recovery would require:

- changing a stable game alliance ID already assigned to a neutral reference;
- merging references solely because names/tags match;
- merging contacts solely because names/handles match;
- linking a contact to player/user/membership identity without a separately approved contract;
- changing another tenant's tracking, observation, diplomacy or contact row;
- rewriting captured Kingdom context after drift;
- editing/deleting append-oriented observation/diplomacy history;
- destructively deleting or rewriting inactive contact history;
- changing diplomacy automatically because review/expiry/contact data changed;
- exposing private relationship/contact data to ordinary members/logs/outbox;
- adding phone/address/credential data to the contact workflow; or
- bypassing `kingdoms.manage` / password confirmation.
