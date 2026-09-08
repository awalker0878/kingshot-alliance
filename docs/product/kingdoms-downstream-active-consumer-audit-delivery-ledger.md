# Kingdoms downstream active-consumer audit — delivery ledger

Status: Complete

- [x] Preserve historical `find()` / `require()` semantics.
- [x] Add explicit active Kingdom lock contracts for transaction-time enforcement.
- [x] Reject Player identity writes into archived Kingdoms.
- [x] Reject Kingdom Governance mutations and administrator bootstrap for archived Kingdoms.
- [x] Reject Alliance writes when the parent Kingdom is archived.
- [x] Reject Event writes when the target or target-parent Kingdom is archived.
- [x] Preserve historical Event target resolution after Kingdom archive.
- [x] Reject Transfer writes when the operating Kingdom is archived.
- [x] Reject mutable Transfer Evidence when its current target Kingdom is archived.
- [x] Move current settings, Governance, roster, intelligence, public, API, bot, recruitment, and broadcast surfaces to active Kingdom semantics.
- [x] Add dedicated downstream active-vs-historical V3 regression coverage.
- [x] Repository CI, Architecture V3, Intelligence Verification, Visual Regression, CodeQL, and Dependency Review green on the verified implementation head `e27e397068b4aaacbc91e0a70e6531e69cb0f8f2` before this documentation-only closeout commit.

## Verification evidence

The verified implementation head passed the full repository gate set before this ledger-only closeout update:

- CI — success, including frontend checks, fresh PostgreSQL installation, PHP formatting/static analysis, 660 PHPUnit tests / 70,682 assertions, container staging, backup/restore, and image scan.
- Architecture V3 Verification — success, including PSR-4 validation, PHP syntax, route boot, architecture tests, fresh schema migration, static analysis, and the full V3 PHPUnit suite.
- Intelligence Verification — success for backend contracts and Intelligence history frontend contracts.
- Visual Regression — success, including production frontend build, application startup, and Playwright visual regression.
- CodeQL — success.
- Dependency Review — success.

The closeout commit changes documentation only; it does not alter executable code, dependency manifests, build configuration, or test behavior.
