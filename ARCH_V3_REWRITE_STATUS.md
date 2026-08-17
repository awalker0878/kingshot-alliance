# Architecture V3 Clean-Room Rewrite Status

Status: **CA-P0 through CA-P14 COMPLETE for the non-visual application/source rewrite.**

Canonical detailed status: `docs/architecture/ARCH-V3-REWRITE-STATUS.md`

Key final state:

- immutable request/security authority boundary;
- Player-scoped game authority;
- context-owned write APIs;
- transaction-time current authority validation;
- capability-first context tree;
- cross-context reads under ReadModels;
- generic Communications delivery boundary;
- V3-only PHPUnit suite under `tests/v3`;
- V2 PHP compatibility tests removed;
- visual/Playwright rewrite explicitly deferred and untouched;
- GitHub workflow/PR-template V3 migration explicitly deferred.

Verification available without Composer dependencies:

- `php tests/v3/Architecture/verify.php`
- `php tests/v3/Architecture/verify-persistence.php`
- `php tests/v3/Architecture/verify-behavior-contracts.php`
- `php tests/v3/Architecture/verify-final-source.php`

Runtime Laravel/PHPUnit/Pint/Larastan execution could not be performed in the supplied archive because `vendor/` is absent, Composer is unavailable, and dependencies cannot be downloaded from this runtime. This is recorded in the canonical status and must be run before merge in the normal dependency-enabled environment.
