# Architecture V3 rewrite status

Branch: `architecture-v3-capability-alignment`

## Overall status

In progress.

Rewrite policy: clean V3 implementation. Do not retain shims, aliases, compatibility namespaces, duplicate legacy models, or transitional persistence facades. When a V3 owner replaces an old implementation path, the old path is deleted and all call sites move to the V3 path.

Testing policy: new rewrite tests are added under `tests/v3` as each phase introduces new code. Existing `/tests` suites are not rewritten merely for compatibility; V3 tests validate the new architecture directly.

Recovery note: temporary phase workflows created before the interruption were removed. Phase completion is now based on the actual source tree and V3 tests, not old workflow/commit labels.

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

### Failed / blocked
- None.

## Phase CA-P2 — Thin HTTP adapters

Status: COMPLETE

### Done
- Added `tests/v3/Architecture/ThinHttpAdaptersV3Test.php` to reject business transactions, locks, direct persistence, and force-fill mutation in Controllers, Middleware, and route files.
- Converted Accounts Profile writes into capability Actions: `UpdateProfile`, `ChangePassword`, and `AuthorizeOtherSessionRevocation`.
- Converted password reset persistence into `Accounts/Credentials/Actions/ResetPassword`.
- Thinned Accounts Profile and ResetPassword controllers to validation/dispatch/session-response behavior only.
- Extracted API credential usage mutation from middleware into `Platform/Integrations/Actions/RecordApiCredentialUse`.
- Extracted KingPerks live no-show replacement transaction into `Operations/KingPerks/Actions/ReplaceNoShowAppointment`.
- Thin-HTTP audit passes with no remaining controller, middleware, or route-owned business write/lock violations.
- Strict PSR-4 optimized autoload validation passes.
- PHP syntax validation passes across app/bootstrap/config/database/routes/tests-v3.
- Application routes boot successfully with `php artisan route:list`.

### Failed / blocked
- None.

## Phase CA-P3 — Context-owned write APIs

Status: IN PROGRESS

### Done
- Added immutable owner/reference contracts and owner Queries across Accounts, GameWorld and Alliance during the initial P3 slices.
- Moved several Platform cross-context mutations to owner Actions, including Alliance lifecycle and Operations Event-type scope changes.
- Reworked Platform Administrator authority around scalar `user_id` grants and Accounts identity snapshots.
- Reworked account deletion/legal hold/platform Alliance administration toward owner-coordinated APIs.
- Removed a set of foreign-context row locks and direct foreign aggregate writes.
- Added `tests/v3/Architecture/CrossContextPersistenceV3Test.php`.
- Removed interrupted P3 audit dumps and all temporary self-writing `v3-*` workflows after recovery.
- Added permanent read-only `architecture-v3-verification.yml` for subsequent validation.

### Remaining
- Eliminate all remaining foreign context Model imports from business contexts in favour of scalar IDs, value objects and owner contracts.
- Remove remaining cross-context Eloquent relationships/navigation.
- Correct `Alliance/Membership/AcceptInvitation`, which still imports/queries Accounts `User` and GameWorld `Player` directly.
- Re-run strict autoload, syntax, route boot and V3 architecture suite at the stable head.

### Failed / blocked
- The interrupted automation previously marked P3/P4/P5/P6 closed without all intended source rewrites being applied. Those labels are discarded; actual source is authoritative.

## Phase CA-P4 — Workflow correction

Status: NOT STARTED

### Known remaining work
- `Workflows/PlayerContext` still exists and must move to `GameWorld/Players`.
- `Workflows/Registration` still exists and must become `Workflows/AccountOnboarding`.
- Only `AccountOnboarding` and `KingdomGovernance` may remain under `app/Workflows`.
- Workflows must contain process coordination only: no transaction ownership, foreign persistence, models, repositories or permission vocabularies.

## Phase CA-P5 — Communications cleanup

Status: NOT STARTED

### Current observation
- Communications is physically reduced to `Delivery`, but semantic/import enforcement must be revalidated after P3/P4.

## Phase CA-P6 — Architecture enforcement

Status: NOT STARTED

### Current observation
- Several V3 structural tests already exist, but the full invariant suite is not considered complete until P3/P4/P5 are clean.

## Phase CA-P7 — Full leakage scan and certification

Status: NOT STARTED

### Current observation
- A pre-crash scan was attempted, but its report push raced with self-writing workflows. P7 will be rerun from a stable branch after P3-P6 are genuinely complete.
