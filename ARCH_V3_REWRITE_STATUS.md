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

### Failed / blocked
- None.

## Phase CA-P2 — Thin HTTP adapters

Status: COMPLETE

### Done
- Added `tests/v3/Architecture/ThinHttpAdaptersV3Test.php` to reject business transactions, locks, direct persistence, and force-fill mutation in Controllers, Middleware, and route files.
- Converted Accounts Profile writes into capability Actions: `UpdateProfile`, `ChangePassword`, and `AuthorizeOtherSessionRevocation`.
- Converted password reset persistence into `Accounts/Credentials/Actions/ResetPassword`.
- Thinned Accounts Profile and ResetPassword controllers to validation/dispatch/session-response behavior only.
- Moved Registration workflow persistence out of its controller into `Workflows/Registration/Actions/RegisterAccount` pending CA-P4 workflow correction.
- Moved Player activation locking/audit out of its controller into `Workflows/PlayerContext/Actions/ActivatePlayer` pending CA-P4 relocation to GameWorld/Players.
- Extracted API credential usage mutation from middleware into `Platform/Integrations/Actions/RecordApiCredentialUse`.
- Extracted KingPerks live no-show replacement transaction into `Operations/KingPerks/Actions/ReplaceNoShowAppointment`.
- Corrected the HTTP invariant to distinguish zero-argument Eloquent `save()`/`delete()` from application methods named `save`/`delete`.
- Thin-HTTP audit passes with no remaining controller, middleware, or route-owned business write/lock violations.
- Strict PSR-4 optimized autoload validation passes.
- PHP syntax validation passes across app/bootstrap/config/database/routes/tests-v3.
- Application routes boot successfully with `php artisan route:list`.
- All V3 architecture tests pass.
- Temporary CA-P2 audit and validation workflows removed after successful execution.

### Validation issues found and resolved
- RallyGuidanceController and PlatformAdministrationController were initially reported due to application helper/action methods named `save`/`delete`; the invariant was narrowed to actual zero-argument Eloquent mutations instead of forcing artificial renames.
- The KingPerks controller contained a real multi-step transaction; it was moved to an owner Action rather than hidden behind a controller helper.

### Intentionally deferred
- `Workflows/Registration` still owns a cross-context transaction; CA-P4 will replace it with `AccountOnboarding` orchestration and owner-controlled transactions.
- `Workflows/PlayerContext` remains until CA-P4 and will move to GameWorld/Players.
- Cross-context ORM relationships, foreign model mutation, foreign locks, and foreign table access are CA-P3 work.

### Failed / blocked
- None.

## Phase CA-P3 — Context-owned write APIs

Status: IN PROGRESS

### Planned work
- Add V3 structural leakage tests that inventory cross-context Eloquent relationships, foreign model mutation/locks, and unsupported direct context persistence access.
- Remove Game/Platform cross-context ORM navigation beginning with PlatformAdministrator -> Accounts User.
- Replace foreign aggregate mutation/locking with owner Actions and stable scalar identifiers.
- Replace direct foreign table/model reads used for business decisions with owner Queries/contracts where appropriate.
- Remove Alliance writes into Platform tables and Platform writes into Alliance/Accounts/GameWorld aggregates.
- Preserve visible ownership boundaries instead of creating compatibility façades.
- Validate autoload, syntax, application boot, and all V3 architecture tests after each corrected slice.

### Done
- Phase started after CA-P2 completed with all validation gates green.

### Failed / blocked
- None.

## Phase CA-P4 — Workflow correction

Status: NOT STARTED

## Phase CA-P5 — Communications cleanup

Status: NOT STARTED

## Phase CA-P6 — Architecture enforcement

Status: NOT STARTED

## Phase CA-P7 — Full leakage scan and certification

Status: NOT STARTED
