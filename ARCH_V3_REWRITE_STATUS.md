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
- Context-root technical buckets declared invalid for capability-owned code.
- Cross-context persistence/navigation rules documented.
- Clean-rewrite/no-shim policy confirmed.
- V3 testing contract retained in `/docs`; implementation tests are created only under `tests/v3` during the rewrite.

### Remaining
- None.

### Failed / blocked
- None.

## Phase CA-P1 — Capability-first physical reorganization

Status: COMPLETE

### Done
- Accounts reorganized into Identity, Registration, Authentication, Credentials, EmailVerification, Profile, and MultiFactorAuthentication.
- GameWorld reorganized into Players, Kingdoms, Governance, and KingdomTransfers.
- Alliance `Core` replaced by `Lifecycle`; root `Alliance/Policies` removed.
- Alliance quota policy split by owner into Membership member capacity and Content storage capacity.
- Operations `EventCore` replaced by `Events`.
- Intelligence root HTTP controller moved to Diplomacy.
- Communications `Reminders` removed; source-domain reminder completion handlers moved to Operations Participation/KingPerks.
- Platform root Actions/Models/Services/Queries/Http/Access/Providers removed and code assigned to Administration, AllianceAdministration, DataGovernance, EventAdministration, Integrations, or Shared Infrastructure.
- Shared root Access/Http/Providers consolidated beneath `Shared/Infrastructure`.
- Application service-provider hub split into capability-owned providers plus Shared Infrastructure provider.
- Runtime imports, routes, bootstrap configuration, auth configuration, factories, and namespaces updated to V3 paths.
- Obsolete package paths deleted instead of retained as aliases or compatibility shims.
- Added `tests/v3/Architecture/CapabilityFirstSourceLayoutV3Test.php`.
- Added `tests/v3/Architecture/NamespaceLocationV3Test.php`.
- Verified exactly seven context roots and target capability roots for every context.
- Verified Shared exposes only `Infrastructure` at its root.
- Strict Composer PSR-4 autoload validation passes.
- PHP syntax validation passes across app/bootstrap/config/database/routes/tests-v3.
- CA-P1 V3 architecture tests pass.

### Validation issues found and resolved
- Moved Shared base Controller initially retained the old namespace; corrected to `App\Shared\Infrastructure\Http\Controller`.
- Initial V3 test namespace capitalization did not match the required lowercase `tests/v3` path; corrected without renaming the requested test directory.
- Structural test initially treated `Operations/Events` as a forbidden technical `Events` folder; corrected because Events is a legitimate V3 capability.
- Two Membership actions still referenced the deleted root Alliance capacity policy; rerouted to `Membership/Policies/MemberCapacityPolicy`.
- Temporary mechanical-refactor/validation workflows were removed after successful execution.

### Intentionally deferred to later phases
- `Workflows/PlayerContext` and `Workflows/Registration` remain until CA-P4 workflow correction.
- Cross-context persistence/ORM leakage exposed by the reorganization remains visible until CA-P3 rather than being hidden behind compatibility helpers.
- HTTP write logic remains for CA-P2.

### Failed / blocked
- None.

## Phase CA-P2 — Thin HTTP adapters

Status: IN PROGRESS

### Planned work
- Add V3 structural test that rejects business transactions, direct persistence, and business locking in Controllers, Middleware, and route closures.
- Inventory every current HTTP write violation on the V3 tree.
- Move each write into the owning capability Action/service without introducing controller helpers or compatibility facades.
- Keep HTTP adapters limited to validation/context resolution, dispatch, and response rendering.
- Validate autoload, syntax, application boot/routes, and V3 tests after conversion.

### Done
- Phase started after CA-P1 validation completed successfully.

### Failed / blocked
- None.

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
