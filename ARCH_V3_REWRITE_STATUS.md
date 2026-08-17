# Architecture V3 rewrite status

Branch: `architecture-v3-capability-alignment`

## Overall status

In progress.

Rewrite policy: clean V3 implementation. Do not retain shims, aliases, compatibility namespaces, duplicate legacy models, or transitional persistence facades. When a V3 owner replaces an old implementation path, the old path is deleted and all call sites move to the V3 path.

Testing policy: new rewrite tests are added under `tests/v3` as each phase introduces new code. Existing `/tests` suites are not rewritten merely for compatibility; V3 tests validate the new architecture directly.

Recovery note: temporary phase workflows created before the interruption were removed. Phase completion is now based on the actual source tree and V3 tests, not old workflow/commit labels.

## Phase CA-P0 — Establish architecture rules

Status: COMPLETE

## Phase CA-P1 — Capability-first physical reorganization

Status: COMPLETE

## Phase CA-P2 — Thin HTTP adapters

Status: COMPLETE

## Phase CA-P3 — Context-owned write APIs

Status: IN PROGRESS

### Done
- Added immutable owner/reference contracts and owner Queries across Accounts, GameWorld and Alliance.
- Moved invitation acceptance cross-context orchestration to `Workflows/AccountOnboarding` and kept Alliance membership writes inside Alliance.
- Player claim and activation are owned by GameWorld/Players and use scalar `user_id` at context boundaries.
- Removed the stale `Workflows/PlayerContext` package; active Player context now belongs to GameWorld/Players.
- Removed Alliance -> GameWorld `Kingdom` Eloquent navigation from `Alliance`; `kingdom_id` remains the scalar boundary.
- Converted `PlayerOwnershipQuery`, `PlayerContext`, and `ResolvePlayerContext` away from Accounts `User` model dependencies.
- Repaired the V3 relationship-enforcement regex so architecture failures are reported instead of throwing on an unset optional alias capture.
- Previous verifier established Composer install, strict optimized PSR-4, PHP syntax, and route boot as green before the remaining architecture assertions.

### Remaining
- Eliminate the remaining foreign context Model imports across Alliance, GameWorld, Intelligence, Operations, and Platform. The last authoritative scan exposed hundreds of legacy foreign-model imports, so P3 remains application-wide work rather than a small residual cleanup.
- Remove all remaining cross-context Eloquent relationships/navigation.
- Move repeated active-player/account/alliance/kingdom authorization lookups to scalar IDs, immutable owner references and owner Queries.
- Remove foreign permission vocabulary imports from write actions/services.
- Remove database lock/transaction acquisition from authorization/access services.
- Re-run strict autoload, syntax, route boot and V3 architecture suite from the stable head.

### Failed / blocked
- No external blocker. The current blocker to phase closure is remaining source leakage reported by the strict V3 architecture suite.

## Phase CA-P4 — Workflow correction

Status: IN PROGRESS

### Done
- `Workflows/Registration` has been replaced by `Workflows/AccountOnboarding`.
- `Workflows/PlayerContext` has been removed; Player activation belongs to GameWorld/Players.

### Remaining
- Correct `Workflows/KingdomGovernance` so it owns no cross-context transaction and imports no foreign context Eloquent models.
- Only `AccountOnboarding` and `KingdomGovernance` may remain under `app/Workflows`.

## Phase CA-P5 — Communications cleanup

Status: NOT STARTED

### Current observation
- Communications is physically reduced to `Delivery`, but semantic/import enforcement must be revalidated after P3/P4.

## Phase CA-P6 — Architecture enforcement

Status: NOT STARTED

### Current observation
- V3 structural tests exist and are intentionally strict. Full invariant closure follows P3/P4/P5.

## Phase CA-P7 — Full leakage scan and certification

Status: NOT STARTED

### Current observation
- P7 will be rerun from a stable branch after P3-P6 are genuinely complete.
