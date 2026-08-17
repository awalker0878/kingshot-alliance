# CA-P13 — Full Behavioral Reconstruction

Status: **PASS — non-visual behavioral suite reconstructed**

## Completed

- Replaced the active PHPUnit suite with `tests/v3`; `phpunit.xml` no longer executes `tests/v2`.
- Removed the obsolete V2 PHP test suite rather than preserving legacy namespaces/contracts for test compatibility.
- Kept `tests/v2/Visual/ApplicationShellV2.spec.ts` untouched and outside this phase; visual coverage is explicitly deferred to its own rewrite.
- Added V3 test support built around final contracts: `RegisteredAccount`, `PlayerReference`, `KingdomReference`, `AllianceReference`, scalar IDs, and immutable account authority.
- Ported durable behavioral intent for:
  - account registration/audit/outbox;
  - Player identity reuse, account claim exclusivity, request Player context, and session selection;
  - Alliance authority isolation, Alliance creation/R5 bootstrap, and member-capacity policy;
  - Kingdom administrator bootstrap and cross-Kingdom rejection;
  - Operations recurrence policy;
  - King Perk preparation-window policy;
  - Platform Administrator lifecycle and isolation from game authority;
  - neutral shared audit/outbox infrastructure.
- Corrected the old Kingdom bootstrap expectation: repeated bootstrap for the same Player is tested as idempotent, while conflicting/cross-Kingdom bootstrap remains rejected.
- Removed the last live-model Platform transaction-authority result. `PlatformMutationContext` now carries immutable `AccountIdentity` plus scalar `grantId` only.
- Added dependency-free behavior-contract verification and wired it into the V3 PHPUnit architecture suite.
- Added PHPUnit wrappers for the persistence gate and behavior-contract gate.

## Verification

- `php tests/v3/Architecture/verify-behavior-contracts.php`: **PASS**
- `php tests/v3/Architecture/verify.php`: **PASS**
- `php tests/v3/Architecture/verify-persistence.php`: **PASS**
- PHP syntax scan across application/routes/config/database/V3 tests: **PASS**
- Project import-resolution scan: **0 missing project imports**
- `tests/v2` PHP tests remaining: **0**
- Deferred visual spec modified: **NO**

## Runtime test disposition

The supplied archive contains no `vendor/autoload.php`, the container has no Composer executable, and outbound DNS/network access from the runtime is unavailable. Therefore Laravel/PHPUnit, Pint and Larastan could not be executed in this environment.

This is an environment limitation rather than a source rewrite blocker. The V3 PHPUnit suite is wired and ready for execution in the normal dependency-enabled development/CI environment before merge.

Known source blockers: **NONE**

Safe to proceed to CA-P14: **YES**
