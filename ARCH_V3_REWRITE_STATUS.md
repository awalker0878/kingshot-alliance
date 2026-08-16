# Architecture V3 rewrite status

Branch: `architecture-v3-capability-alignment`

## Overall status

In progress.

Rewrite policy: clean V3 implementation. Do not retain shims, aliases, compatibility namespaces, duplicate legacy models, or transitional persistence facades. When a V3 owner replaces an old implementation path, the old path is deleted and all call sites move to the V3 path.

Testing policy: new rewrite tests are added under `tests/v3` as each phase introduces new code. Existing `/tests` suites are not rewritten merely for compatibility; V3 tests validate the new architecture directly.

## Phase CA-P0 — Establish architecture rules

Status: COMPLETE

### Done
- Architecture V3 documentation established under `/docs`.
- Exact seven-context capability-first end-state tree defined.
- Context, Capability, Technical layer, Workflow, ReadModel, and Shared invariants documented.
- Context-root `Actions`, `Models`, `Queries`, `Services`, `Policies`, and `Http` buckets declared invalid for capability-owned code.
- Cross-context persistence/navigation rules documented.
- Clean-rewrite/no-shim policy confirmed.
- V3 testing contract retained in `/docs`; implementation tests will be created only under `tests/v3` during the rewrite.
- Initial source inventory confirms the current code still contains pre-V3 structures, including Accounts context-root technical buckets, so CA-P1 has concrete work to perform.

### Remaining
- None.

### Failed / blocked
- None.

## Phase CA-P1 — Capability-first physical reorganization

Status: IN PROGRESS

### Planned work
- Reorganize Accounts into Identity, Registration, Authentication, Credentials, EmailVerification, Profile, and MultiFactorAuthentication.
- Reorganize GameWorld into Players, Kingdoms, Governance, and KingdomTransfers.
- Reorganize Platform into Administration, AllianceAdministration, DataGovernance, EventAdministration, and Integrations.
- Remove Alliance root technical/policy buckets and align all Alliance code to Lifecycle, Membership, Access, Recruitment, and Content.
- Remove Intelligence root technical buckets and align all Intelligence code to its V3 capabilities.
- Align Operations and Communications physical names to the V3 capability tree.
- Update namespaces/imports/routes/providers required by movement only; do not intentionally change domain behavior in CA-P1.
- Add V3 structural tests for the physical source layout introduced by this phase.

### Done
- Current Accounts tree inventoried; it still uses root `Actions`, `Http`, `Models`, and `Services` and will be split first.

### Failed / blocked
- None.

## Phase CA-P2 — Thin HTTP adapters

Status: NOT STARTED

## Phase CA-P3 — Context-owned write APIs

Status: NOT STARTED

## Phase CA-P4 — Workflow correction

Status: NOT STARTED

## Phase CA-P5 — Communications cleanup

Status: NOT STARTED

## Phase CA-P6 — Architecture enforcement

Status: NOT STARTED

## Phase CA-P7 — Full leakage scan and certification

Status: NOT STARTED
