# KINGDOMS-001 implementation plan

[← Kingdoms roster intelligence product increment](kingdoms-roster-intelligence-increment.md)

**Status:** Accepted — `K1-P0` through `K1-P6` complete  
**Scope ID:** `KINGDOMS-001`  
**Owning domain:** `Kingdoms`  
**Baseline:** Approved `KINGDOMS-001` scope and accepted Phase 0–6 engineering controls  
**Important:** These are implementation phases inside `KINGDOMS-001`; they are not a continuation of the historical program phase numbering.

## 1. Purpose

This plan converts the approved `KINGDOMS-001` scope into gated implementation phases that can be reviewed and validated independently while preserving one end-to-end acceptance boundary for the increment.

The implementation must continue to follow the repository's established principles:

- domain-first runtime ownership under `app/Domain/<Domain>`;
- explicit alliance tenancy for tenant-owned observations and workflows;
- global identity/reference data only where the product model requires it;
- policy/permission authorization rather than controller role-name checks;
- thin controllers and business logic in actions/services/queries;
- transactional persistence for related business mutations;
- audit events for privileged changes;
- transactional outbox for durable cross-domain or external side effects;
- append-oriented history where historical observations matter;
- no compatibility shims after a migration is complete;
- code and tests authoritative for exact runtime behavior;
- security, accessibility, operations and living documentation updated in the same change when affected; and
- no partially implemented future capabilities hidden behind unused schema or UI placeholders.

## 2. Phase summary

| Phase | Status | Outcome | Primary slice |
| --- | --- | --- | --- |
| `K1-P0` | Complete | Design and migration contract locked | Slice A preparation |
| `K1-P1` | Validated | First-class Kingdom foundation | Slice A |
| `K1-P2` | Validated | Game-player and alliance roster foundation | Slice B |
| `K1-P3` | Validated | Historical player snapshots | Slice C1 |
| `K1-P4` | Validated | Roster intelligence and trend views | Slice C2 |
| `K1-P5` | Validated | Controlled CSV migration workflow | Slice D |
| `K1-P6` | Accepted | Increment hardening and acceptance | Whole increment |

Each implementation phase left the repository internally consistent. `K1-P1` through `K1-P5` passed their protected slice gates; `K1-P6` then validated the complete dependency stack end to end. `KINGDOMS-001` is Accepted with exact evidence recorded in the [exit report](kingdoms-roster-intelligence-exit-report.md).

## 3. K1-P0 — Design and migration contract

### Objective

Lock the data, tenancy, authorization and migration boundaries before runtime schema is changed.

### Deliverables

- Confirm `Kingdom` is global reference data keyed by canonical kingdom number.
- Confirm `KingdomPlayer` is global neutral game identity/reference data and does not own alliance-private observations.
- Confirm alliance roster entries and player snapshots are alliance-scoped.
- Define the exact migration/backfill rule from the current nullable `alliances.kingdom` string.
- Define how malformed or non-normalizable legacy values are handled without silent data loss.
- Define the final alliance schema after migration; do not retain a permanent compatibility field.
- Confirm ordinary roster visibility uses `alliance.view` and mutations use the new `kingdoms.manage` permission.
- Confirm Owner/Leader/Officer built-in defaults receive `kingdoms.manage`; other built-in roles do not.
- Identify current public-profile/create-alliance flows that write/read the legacy kingdom string and define their new contract.
- Decide whether any material architecture choice requires a new ADR; if not, update living architecture/domain documentation only.

### Verification gate

- Migration design is reversible for development/test rollback and preserves all normalizable legacy values.
- No proposed global Kingdom/KingdomPlayer query creates cross-alliance access to roster notes, snapshots, membership links, imports, exports or metrics.
- No future transfer/diplomacy/automated-ingestion field is added merely as a placeholder.

## 4. K1-P1 — First-class Kingdom foundation

### Objective

Replace the free-form alliance kingdom field with a real Kingdom aggregate and an audited, authorized alliance relationship.

### Backend and persistence

- Add `Kingdom` model under `app/Domain/Kingdoms/Models` using the repository's ULID conventions.
- Add a kingdom lifecycle enum/value where needed, initially `active` and `archived`.
- Add `kingdoms` persistence with a unique canonical kingdom number.
- Add nullable `kingdom_id` to alliances during the migration transition.
- Backfill distinct normalizable legacy alliance kingdom values into canonical Kingdom records.
- Backfill `alliances.kingdom_id` from those canonical records.
- Remove the legacy `alliances.kingdom` column in the same completed migration series; runtime code must not support both representations indefinitely.
- Add the `Alliance::kingdom()` relationship and remove legacy string fillable/runtime access.
- Update alliance fixtures/tests to produce valid Kingdom relationships.

### Domain actions

- Add a Kingdoms-domain action/query contract for resolving or creating a canonical Kingdom by normalized kingdom number where creation is permitted.
- Add an audited alliance kingdom-association action using `AllianceAuthorization` and `PermissionKey::AllianceManage` for the Slice-A association change.
- Require recent password confirmation at the HTTP route for the privileged association mutation.
- Record an audit event with previous/new kingdom references.
- Record a transactional-outbox event when the alliance kingdom association materially changes.

`kingdoms.manage` is introduced in `K1-P2` for roster mutations. Slice A must not prematurely use it for a roster surface that does not yet exist.

### Existing workflow changes

- Alliance creation accepts a canonical kingdom number rather than arbitrary kingdom display text and resolves it through the Kingdoms contract.
- Content/public-profile management must stop owning kingdom mutation. Kingdom association is not content metadata.
- Public and member alliance presenters load the Kingdom relation and expose the canonical kingdom number as display data where the existing UI expects a kingdom value.
- Integrations read-only alliance output continues to expose the existing `kingdom` response field for API compatibility, but derives that value from the Kingdom relation. This is a supported API representation, not a persistence shim.

### UI

- Keep alliance creation simple: kingdom number input with clear validation.
- Add a dedicated alliance-settings control for changing kingdom association, visible only to users allowed to manage alliance settings.
- Do not place kingdom mutation inside Content management after Slice A.

### Tests

- migration/backfill tests for unique and shared kingdom values;
- malformed/null legacy-value handling validation;
- alliance creation tests with canonical kingdom resolution;
- alliance kingdom-change authorization/password-confirmation tests;
- audit/outbox assertions;
- tenant-isolation tests proving another alliance cannot mutate the active alliance's kingdom association through submitted IDs;
- API/public/member presentation regression tests; and
- architecture tests confirming Kingdom runtime PHP remains under the canonical domain root.

### Exit criteria

- No runtime code reads or writes a free-form `alliances.kingdom` column.
- Existing alliance kingdom values are preserved when normalizable.
- Multiple alliances can reference the same Kingdom without gaining access to each other's tenant data.
- Alliance creation, public/member presentation and read-only API behavior remain functional.
- Privileged kingdom changes are authorized, password-confirmed, audited and published through the outbox.

## 5. K1-P2 — Game-player and alliance roster foundation

### Objective

Introduce game identity and an alliance-owned roster without conflating application identity, membership and Kingshot identity.

### Persistence

- Add global neutral `KingdomPlayer` records owned by Kingdoms.
- Support optional stable game-player identifier when known.
- Do not enforce player-name uniqueness.
- Add alliance-scoped roster entries linking `alliance_id` to `kingdom_player_id`.
- Add optional same-alliance `membership_id` link.
- Add roster state, game role/rank, joined/left dates, private manager notes, last-observed timestamp and source/provenance fields required by the approved scope.
- Add database constraints/indexes that support tenant-scoped queries and prevent duplicate active roster relationships where appropriate.

### Authorization

- Add `PermissionKey::KingdomManage = 'kingdoms.manage'`.
- Update built-in defaults:
  - Owner: yes through all permissions;
  - Leader: yes;
  - Officer: yes;
  - Recruiter/Event Coordinator/Content Manager/Member: no.
- Preserve custom-role permission union semantics.
- Ordinary authenticated roster view requires `alliance.view`.
- All roster mutations require `kingdoms.manage` plus recent password confirmation.

### Domain behavior

- Add actions for create/update roster entry, link/unlink membership and mark player left.
- Re-resolve all submitted roster/player/membership IDs under the active alliance boundary before mutation.
- A membership may link only when it belongs to the same alliance.
- Membership departure/removal does not delete the roster/game-player history.
- Game-player merge/deduplication must never occur solely by display name.
- Privileged mutations emit audit records and appropriate outbox events.

### UI

- Add Alliance → Roster member view.
- Add Roster management view for authorized managers.
- Support search/filter by state, linked/unlinked membership, game role/rank and stale/missing state available at this phase.
- Surface both linkage gaps: membership without roster profile and roster profile without membership.

### Tests and exit criteria

- CRUD/authorization/password-confirmation tests;
- membership-link invariant tests;
- cross-alliance object-ID tampering tests;
- display-name collision tests;
- audit/outbox assertions;
- accessibility guard for roster/manage surfaces; and
- no cross-alliance roster data disclosure.

## 6. K1-P3 — Historical player snapshots

### Objective

Make observed game state append-oriented and historically explainable.

### Persistence and behavior

- Add alliance-scoped player snapshots tied to the roster/player identity.
- Store observed name, power, optional progression/level, observed alliance/tag, capture time, source and actor/import provenance.
- Use integer/numeric types appropriate to expected Kingshot power ranges without floating-point loss.
- Define idempotency for retrying the same accepted observation.
- Preserve legitimate repeated observations at later capture times.
- Maintain an intentional latest-observation projection/query; do not make a duplicated mutable current-value column the historical source of truth unless justified by measured query needs.

### UI

- Authorized managers can record a manual snapshot.
- Members can view current/latest roster values allowed by roster visibility.
- Snapshot history is available under the tenant boundary.
- Stale/missing observation indicators become functional.

### Tests and exit criteria

- append/history tests;
- idempotency tests;
- integer-range tests;
- tenant-isolation tests;
- latest-projection correctness tests;
- missing-data semantics tests; and
- no prior observation is destroyed by a normal update.

## 7. K1-P4 — Roster intelligence and trends

### Objective

Turn snapshots into useful alliance operational insight without creating opaque or punitive scoring.

### Metrics

Implement from recorded data only:

- active tracked-player count;
- total recorded power;
- average and median power;
- stale/missing observation counts;
- recent joins and departures;
- membership/game-profile linkage coverage;
- aggregate 7-day power change; and
- aggregate 30-day power change.

### Calculation rules

- Distinguish missing data from zero.
- State the effective observation window used by each trend.
- Use nearest eligible observations under a documented rule when exact 7/30-day snapshots do not exist.
- Do not fabricate interpolated precision unless explicitly designed and tested.
- Individual growth/decline comparison remains a manager-controlled view and must not become an automatic punitive score.
- Power growth is not a Contribution record.

### UI and observability

- Add roster intelligence dashboard.
- Make stale/missing data visibly distinct from actual zero/decline.
- Add enough structured diagnostics to troubleshoot calculation/data-quality failures without logging private notes.

### Tests and exit criteria

- deterministic trend-calculation tests with irregular observations;
- median/aggregate correctness tests;
- missing-data tests;
- tenant-isolation tests; and
- accessibility validation of tables/filters/summary cards.

## 8. K1-P5 — Controlled CSV migration workflow

### Objective

Allow alliances to leave spreadsheets safely without making imports an uncontrolled identity-merging path.

### Import contract

- Document an explicit CSV schema.
- Parse uploaded CSV without executing spreadsheet content.
- Enforce file/row/field limits.
- Validate all rows before commit.
- Present a dry-run preview containing creates, updates, ambiguous matches and rejected rows.
- Require explicit confirmation before persistence.
- Match by stable game identifier when available.
- Never merge solely by display name.
- Require explicit user resolution for ambiguous identity.
- Persist import provenance and an auditable result summary.
- Make confirmation idempotent so retrying the same accepted import cannot multiply roster/snapshot rows.

### Export contract

- Provide alliance-scoped current roster export.
- Sanitize cells against spreadsheet formula injection.
- Exclude private notes from ordinary member export.
- Require `kingdoms.manage` for management-only export fields.
- Mark responses private/non-cacheable as appropriate.

### Tests and exit criteria

- malformed/oversized-file tests;
- CSV formula-injection tests;
- ambiguous-name tests;
- preview-versus-commit consistency tests;
- idempotent confirmation tests;
- authorization/tenant-isolation tests; and
- realistic roster-size performance validation.

## 9. K1-P6 — Hardening and increment acceptance

### Objective

Validate the complete `KINGDOMS-001` contract end to end and produce acceptance evidence.

### Required review

- full domain-boundary review;
- threat/security review for roster disclosure, private notes, object-ID tampering, imports/exports and identity ambiguity;
- accessibility review of creation/settings/roster/history/dashboard/import workflows;
- migration/rollback validation from the accepted pre-increment schema;
- query/index review for roster and snapshot history volumes;
- observability and operational documentation review;
- API/webhook compatibility review confirming no undocumented exposure was introduced;
- capability matrix/current architecture update from Planned to Implemented only after acceptance; and
- dedicated `KINGDOMS-001` exit record with validated SHA and protected-check evidence.

### Acceptance result

Accepted. The whole-increment implementation head `7f743507b70865692290f517cd2de494ec54abae` passed Dependency Review, CodeQL, frontend quality/build, PostgreSQL migrations, Pint, PHPStan, 238 tests / 2,556 assertions, the 150-player / 450-snapshot query regression, immutable-image staging, backup/restore and image scanning. The final evidence and the cross-slice webhook hardening finding are recorded in the [KINGDOMS-001 exit report](kingdoms-roster-intelligence-exit-report.md).

Candidate follow-ons remain unimplemented and must not appear as current runtime capability.

## 10. Pull-request sequencing

Completed implementation sequence:

1. **Slice A / `K1-P1` — Kingdom foundation** (including final `K1-P0` design decisions).
2. **Slice B / `K1-P2` — Roster foundation**.
3. **Slice C1 / `K1-P3` — Snapshot history**.
4. **Slice C2 / `K1-P4` — Intelligence dashboard**.
5. **Slice D / `K1-P5` — CSV migration workflow**.
6. **`K1-P6` — Hardening, audits, documentation and acceptance record**.

Every slice remains independently deployable/migratable and the accepted increment contains no dormant follow-on schema or compatibility code merely to make a future increment easier.
