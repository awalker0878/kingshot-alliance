# Kingdom Transfer Planning reference

Status: Current — 2026-08-23

Owner: `GameWorld/KingdomTransfers`

Product contract: [`../product/kingdom-transfer-planning.md`](../product/kingdom-transfer-planning.md)

Architecture: [`../architecture/contexts/game-world/kingdom-transfers.md`](../architecture/contexts/game-world/kingdom-transfers.md)

## Boundary

The HTTP surface is Alliance-scoped and uses the active Player established by `alliance.context`. Read routes require the transfer view boundary; writes require `kingdom_transfer.manage`, password confirmation, and concrete owner-scope authorization inside the application Action.

Frontend permission flags control affordances only. They are not authorization evidence.

## Read routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/alliance/transfers` | Transfer plan overview. |
| `GET` | `/alliance/transfers/manage` | Transfer Window, official game-fact, cohort and participant management. |
| `GET` | `/alliance/transfers/readiness` | Decision-first participant eligibility and independent workflow readiness. |
| `GET` | `/alliance/transfers/completion` | Final transfer outcome workflow. |

The readiness response composes, for each participant, the selected Transfer Window, target Kingdom, official phase, official Transfer Group, applicable target condition observation, selected Governor observations, structured eligibility assessment, planning readiness, cohort, manual blockers and relevant history.

## Transfer Window writes

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/windows` | Record a Transfer Window and explicit phase boundaries. |
| `PATCH` | `/alliance/transfers/windows/{window}` | Correct a window before its Pre-Transfer phase starts. |
| `POST` | `/alliance/transfers/windows/{window}/official-groups` | Record/revise an official Transfer Group and its window-scoped Kingdom membership. |
| `POST` | `/alliance/transfers/windows/{window}/conditions` | Record a target Kingdom condition observation such as Power Cap/classification. |

Window/group/condition writes require explicit provenance. Official game facts are not accepted as timeless Kingdom attributes.

### Transfer Window fields

Window input carries a label and strictly increasing UTC boundaries for:

- `starts_at`;
- `invitational_starts_at`;
- `transfer_opens_at`;
- `ends_at`.

It also carries source type/reference, `observed_at`, and optional evidence reference where available. A window is immutable once its Pre-Transfer phase has begun.

### Official Transfer Group fields

An official Transfer Group record includes its window, official label, Kingdom membership, source/reference and `observed_at`. Re-recording identical evidence is idempotent. A correction creates a new revision and supersedes the previous current revision; a Kingdom cannot belong to two current official groups in the same window.

### Target condition fields

Target Kingdom condition observations are append-only and window/Kingdom-scoped. Supported typed facts include the observed Power Cap and Kingdom classification. Corrections preserve prior records rather than rewriting history.

## Plan and cohort writes

Existing plan, participant, readiness, blocker and completion routes remain supported. The previous Alliance planning `TransferGroup` concept has been renamed cleanly to **Transfer Cohort**; no compatibility route or alias is retained.

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/{plan}/cohorts` | Create a planning cohort. |
| `PATCH` | `/alliance/transfers/{plan}/cohorts/{cohort}` | Update a planning cohort. |
| `POST` | `/alliance/transfers/{plan}/cohorts/{cohort}/archive` | Archive a planning cohort. |
| `PATCH` | `/alliance/transfers/{plan}/participants/{participant}/cohort` | Assign/unassign a participant cohort. |

## Governor observation write

`POST /alliance/transfers/{plan}/participants/{participant}/observations`

The endpoint records one append-only transfer observation. It does not set eligibility.

Observation input is typed by observation kind and may carry:

- target Kingdom when the fact is target-specific;
- numeric, text or boolean value according to kind;
- source type and source reference;
- `observed_at`;
- `valid_until` for mutable Governor facts;
- optional evidence reference;
- optional explanatory details.

Supported transfer observation kinds cover Governor Power, Transfer Score, available Transfer Passes, observed required Transfer Passes, invitation status and the explicit in-game verification gate used for eligibility rules not safely modeled from public authoritative evidence.

The write fingerprint is derived from owner scope plus the normalized observation payload. Retrying the identical record is a no-op and returns the existing observation rather than creating duplicate history.

## Source types

| Source | Eligibility authority |
| --- | --- |
| `official_publication` | May satisfy an authoritative published game fact. |
| `in_game` | May satisfy a fact observed directly in KingShot. |
| `evidence` | May satisfy a fact when backed by an authorized reviewed evidence record. |
| `manager_note` | Planning context only; not authoritative eligibility truth. |
| `community` | Discovery/context only; not authoritative eligibility truth. |

A source reference is mandatory for recorded sourced facts. Mutable Governor observations without an explicit validity boundary cannot silently become current eligibility truth.

## Eligibility response contract

Eligibility is a deterministic response, never a persisted boolean. The server emits:

- overall `outcome`;
- `evaluated_at`;
- `primary_next_action` when applicable;
- ordered `requirements`.

Each requirement contains:

- requirement key;
- state: `met`, `unmet`, `unknown`, `stale`, `conflicting`, or `not_applicable`;
- actual and required values where meaningful;
- human-readable explanation/next action;
- source/reference and observation time for material evidence.

Overall outcomes are `eligible_now`, `eligible_with_action`, `blocked`, `needs_verification`, `not_open_yet`, `window_closed`, or `not_applicable`.

`eligible_now` is impossible when a material requirement is missing, stale, conflicting, non-authoritative or otherwise unverified.

## Transfer Pass rule boundary

The application does **not** invent a public Transfer Pass formula. Where KingShot/public authoritative evidence does not expose a trustworthy calculation, the required-pass value is recorded as an observed sourced fact. The evaluator may compare fresh authoritative available/required observations, but it does not extrapolate an undocumented formula.

## Error semantics

- invalid typed input → validation error;
- wrong Alliance/plan/window/participant scope → authorization/not-found boundary without cross-scope disclosure;
- withdrawn participant → observation mutation rejected;
- mutable observation without valid provenance/freshness → rejected or evaluated as unknown according to the contract;
- stale/conflicting facts → successful read with `needs_verification`, never optimistic eligibility;
- duplicate observation retry → idempotent success/no duplicate row.

## Query budget

Read composition must remain bounded by relation type rather than participant count. Eligibility evaluation operates on preloaded/typed snapshots and must not issue per-requirement database queries from the evaluator or Vue layer.
