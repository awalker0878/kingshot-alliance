# Kingdoms alliance intelligence operations

[← Operations documentation](README.md)

**Scope:** `KINGDOMS-003`  
**Current delivery:** Slice C1 / `K3-P3` candidate

## Runtime ownership

Slices A through C1 are synchronous first-party web workflows using PostgreSQL, the existing audit recorder and transactional outbox. They introduce no Kingdoms scheduler, queue worker, crawler, scraper, OCR process, bot, diplomacy timer or automated game-data ingestion process.

Routes:

- member-safe tracked alliance list: `/alliance/kingdom-alliances`;
- manager tracking workspace: `/alliance/kingdom-alliances/manage`;
- member/manager observation history: `/alliance/kingdom-alliances/{tracking}/history`;
- manager diplomacy workspace: `/alliance/kingdom-alliances/{tracking}/diplomacy`;
- password-confirmed observation mutations under `/alliance/kingdom-alliances/{tracking}/observations`; and
- password-confirmed diplomacy transitions under `/alliance/kingdom-alliances/{tracking}/diplomacy/transitions`.

## Expected durable state

Operators may diagnose current K3 state through:

- `kingdom_alliances` neutral identity rows;
- `tracked_kingdom_alliances` tenant tracking rows;
- `kingdom_alliance_observations` tenant factual history;
- `kingdom_alliance_diplomacy_relationships` tenant current relationship state;
- `kingdom_alliance_diplomacy_transitions` append-oriented relationship history;
- `audit_events`; and
- `outbox_messages`.

## Observation behavior

Observation history remains append-oriented. Exact retry uses deterministic SHA-256 identity, latest accepted projection uses greatest capture time then observation ULID, and invalidated rows remain historical while being excluded from member/latest projection.

Do not directly update/delete observation facts to repair history; use the accepted correction/invalidation actions.

## Diplomacy transitions

Diplomacy is explicit human-maintained state. The only valid states are:

`unknown`, `neutral`, `friendly`, `nap`, `ally`, and `rival`.

A transition request updates the one current relationship row and appends a transition snapshot in the same transaction. The tracking row is the serialization point for relationship creation/change, preventing concurrent duplicate creation under the unique Alliance + tracking constraint.

An exact repeat of the current state/effective/review/expiry/terms/rationale meaning is idempotent and creates no new transition/audit/outbox evidence.

A same-state request with changed metadata is a material update and intentionally appends a new transition. Do not collapse those rows as duplicates.

## Review and expiry

Review and expiry times are advisory only. `needs_review` is derived during reads when either configured time is due.

There is no scheduled diplomacy mutation. If a relationship is still `nap` after expiry, that is expected until an authorized manager explicitly records a new state.

When diagnosing an overdue relationship, do not manually modify `current_state` based only on timestamps.

## Failure modes and recovery

### Alliance has no current Kingdom

Tracking creation fails. Configure the Alliance Kingdom through the accepted Alliance-setting workflow before retrying.

### Stable ID conflict

Assigning a stable game alliance ID that already belongs to another neutral reference in the same Kingdom fails closed. Do not repair this by rewriting IDs directly in PostgreSQL.

### Alliance Kingdom changed

Historical tracking, observation history and diplomacy history remain readable, but normal tracking/observation/diplomacy mutations fail because captured Kingdom context no longer equals the Alliance current Kingdom.

Archive stale tracking if appropriate, then deliberately establish tracking under the new current Kingdom. Do not rewrite `tracked_kingdom_alliances.kingdom_id`, observation foreign keys or diplomacy foreign keys to make old history appear current.

### Archived tracking

Diplomacy history remains manager-readable. New diplomacy transitions are rejected. Do not reactivate history by editing the archived row directly.

### Invalid diplomacy dates

Review and expiry times cannot precede the relationship effective time, and review cannot be later than expiry when both are supplied. Correct the intended planning dates and retry; do not bypass the validation in PostgreSQL.

### Duplicate diplomacy submission

If the submitted state and all normalized relationship metadata already match the current relationship, the action returns the existing row without adding history or durability evidence. This is expected idempotent behavior.

### Outbox publication failure

The business transaction remains committed with an unpublished outbox row. Recover through the existing `outbox:publish` workflow; do not recreate the business mutation solely to publish its event.

## Privacy and diagnostics

Manager tracking notes, observation correction/invalidation reasons, diplomacy terms and diplomacy rationale are private tenant data. Do not copy them into logs, tickets, metrics labels, audit metadata or outbox payloads.

Structured diagnostics should use bounded identifiers/state such as Alliance ID, tracked record ID, neutral reference ID, observation/relationship/transition IDs, captured Kingdom ID, diplomacy from/to state, effective/review/expiry timestamps, freshness/review-due state and event type.

Member-facing tracked-alliance payloads deliberately expose only the current diplomacy label and review-due indicator. Transition IDs/history, actor attribution, manager-private terms/rationale and manager route URLs are omitted.

## Audit/outbox expectations

Material diplomacy changes emit `kingdoms.diplomacy_transitioned` to audit and internal outbox evidence. The payload excludes terms/rationale.

Existing Integration policy excludes all `kingdoms.*` events from generic external webhook fan-out. C1 adds no public API or webhook contract.

## Migration/rollback

Current K3 migrations:

1. `2026_08_09_140000_create_kingdom_alliance_tracking.php`;
2. `2026_08_09_150000_create_kingdom_alliance_observations.php`;
3. `2026_08_10_090000_create_kingdom_alliance_diplomacy.php`.

Rollback order is the reverse: diplomacy transitions/current relationship first, observations second, then tracking/neutral references, followed by the accepted K2/K1 chain.

The C1 migration has no compatibility shim and no contact/player-link/scoring/ingestion/public-integration placeholders.

## Stop conditions

Escalate instead of applying manual data fixes when recovery would require:

- changing a stable game alliance ID already assigned to a neutral reference;
- merging references solely because names/tags match;
- changing another tenant's tracking, observation or diplomacy row;
- rewriting captured Kingdom context after drift;
- editing/deleting append-oriented observation or diplomacy transition history;
- changing diplomacy automatically because review/expiry passed;
- inferring diplomacy from power, observations, combat or transfer state;
- exposing manager notes, observation reasons, diplomacy terms/rationale or actors to ordinary members/logs/outbox;
- adding contact/identity/authorization shortcuts through diplomacy; or
- bypassing `kingdoms.manage` / password confirmation.
