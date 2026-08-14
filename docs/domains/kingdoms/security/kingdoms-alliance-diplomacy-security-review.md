# KINGDOMS-003 Slice C1 diplomacy security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-003` Slice C1 / `K3-P3`  
**Status:** Candidate — protected validation pending

## Security objective

Slice C1 adds explicit diplomacy/NAP lifecycle and append-oriented transition history without weakening active-Alliance tenancy, Kingdom context, authorization, privacy, neutral-reference identity, internal-event, or anti-automation boundaries established by `KINGDOMS-001`, `KINGDOMS-002`, and K3 Slices A/B.

## Tenant and object-ID isolation

Every privileged diplomacy mutation:

1. authorizes `kingdoms.manage` against the active platform Alliance;
2. re-loads and row-locks that Alliance;
3. resolves the submitted `TrackedKingdomAlliance` only under that Alliance ID;
4. requires active tracking;
5. verifies captured tracking Kingdom equals the Alliance current Kingdom;
6. locks/revalidates the neutral `KingdomAlliance` reference against the captured Kingdom; and
7. resolves/creates the one current relationship only under the same Alliance + tracking context.

A tracking ID belonging to another tenant fails closed before relationship lookup/creation. Sharing the same neutral `KingdomAlliance` reference does not grant access to another tenant's diplomacy state/history.

## Authorization and password assurance

- ordinary tracked-alliance reads continue to use `alliance.view`;
- the dedicated diplomacy workspace requires `kingdoms.manage`;
- transition mutation requires `kingdoms.manage` plus recent password confirmation;
- no role-name controller checks are introduced;
- no tracking coordinator/contact assignment can grant `kingdoms.manage`; and
- platform administration does not implicitly become tenant diplomacy management.

## Neutral identity boundary

`KingdomAlliance` remains neutral reference data only. Diplomacy state, dates, private terms/rationale, actors and transition history live on tenant-owned tables.

Diplomacy does not create, merge, relink or authorize platform `Alliance`, `User`, `AllianceMembership`, or `Player` records.

Name/tag/handle values never become identity keys through this slice.

## Explicit-only state mutation

The state vocabulary is fixed to:

`unknown`, `neutral`, `friendly`, `nap`, `ally`, `rival`.

Only an explicit authorized manager action changes current state. The runtime contains no event listener, scheduler, observation hook, transfer hook, combat hook or derived metric capable of changing diplomacy automatically.

Review/expiry dates produce a derived `needs_review` presentation only. Passing those dates never changes state.

## History integrity and concurrency

The tracking row is locked before relationship mutation, making it the serialization point for both first relationship creation and later changes.

One current relationship is enforced by a unique Alliance + tracking constraint.

Every material change updates current state and appends a transition snapshot in the same database transaction. Historical transitions are not updated/deleted by normal lifecycle behavior.

An exact repeat of the current normalized meaning is idempotent and emits no duplicate transition/audit/outbox evidence. A same-state metadata change remains material and appends history.

## Private data boundary

Manager-private fields:

- current terms;
- current rationale;
- historical transition terms/rationale;
- transition IDs;
- transition actors; and
- manager workflow route/internal identifiers not required by members.

Ordinary member payloads receive only current diplomacy state and a derived review-due boolean.

Private terms/rationale are excluded from audit metadata and outbox payloads. They must not be copied into general logs, metrics labels or external integration payloads.

## Kingdom drift and archival

If the platform Alliance changes Kingdom after tracking was established:

- historical diplomacy remains manager-readable;
- normal transition mutation fails closed;
- records are not silently retargeted to the new Kingdom.

Archiving tracking also preserves relationship/history and makes new transitions fail closed.

## Integration boundary

Slice C1 emits only the internal event:

- `kingdoms.diplomacy_transitioned`.

Existing Integration policy rejects all `kingdoms.*` events from generic webhook fan-out, including wildcard subscriptions.

No `/api/v1` Kingdoms/diplomacy route, credential scope, public event schema, shared-Kingdom feed or external contact directory is added.

## Abuse/non-capability review

Slice C1 adds no:

- threat/desirability/combat score;
- ranking or targeting workflow;
- automated diplomacy recommendation;
- automatic NAP/ally/rival inference;
- automatic state change from power/observations/attacks/transfers;
- automated negotiation/message generation;
- contact directory;
- `Player` contact linkage;
- scraping/OCR/bot/automated game ingestion; or
- cross-tenant/shared intelligence.

Architecture tests guard later-slice/contact/scoring/ingestion/public-route fields from the C1 migration/routes.

## Required protected evidence

Before `K3-P3` becomes Validated, the exact runtime SHA must pass:

- PostgreSQL migrations and rollback/reapply coverage;
- transition/idempotency/no-auto-transition feature tests;
- tenant-ID/Kingdom-drift/password-confirmation tests;
- member/private-field isolation tests;
- private terms/rationale audit/outbox safety tests;
- accessibility/source guards;
- Pint and PHPStan/Larastan;
- full test suite;
- frontend lint/format/type/build;
- Dependency Review and CodeQL; and
- immutable image/staging/backup-restore/image scan controls.

Whole-increment `KINGDOMS-003` acceptance remains deferred to `K3-P6`.
