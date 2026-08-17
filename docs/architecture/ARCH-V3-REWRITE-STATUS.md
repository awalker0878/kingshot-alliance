# Architecture V3 Clean-Room Rewrite Status

Baseline branch: `main`
Baseline commit: `e2ddd9fa1cc237c1fc4b15356d81f7ecc6b346bf`
Rewrite policy: clean-room V3; no compatibility shims, dual APIs, legacy authority contracts, or V2 PHP test compatibility suite.
Certification scope: application/source rewrite; visual rewrite and GitHub workflow migration explicitly deferred.

## Overall status

**COMPLETE — CA-P0 through CA-P14 pass for the non-visual application/source rewrite.**

| Phase | Status | Result |
|---|---|---|
| CA-P0 | PASS | Architecture invariants re-established from first principles. |
| CA-P1 | PASS | Capability-first physical source layout certified. |
| CA-P2 | PASS | Request/security and public write boundaries use immutable references/snapshots/scalars rather than Eloquent authority objects. |
| CA-P3 | PASS | HTTP mutation adapters are thin and do not own persistence/transactions. |
| CA-P4 | PASS | Context-owned write APIs replace cross-context Eloquent mutation/navigation. |
| CA-P5 | PASS | Protected writes reacquire mutable authority inside their transaction/lock boundary. |
| CA-P6 | PASS | Workflows coordinate owner APIs and own no domain persistence/permission vocabulary. |
| CA-P7 | PASS | Communications is generic delivery only; Operations owns reminder meaning. |
| CA-P8 | PASS | Cross-context composite reads moved to explicit ReadModels/projections. |
| CA-P9 | PASS | Legacy model/namespace/authority leakage removed; write Actions do not accept or return Eloquent models. |
| CA-P10 | PASS | Structural enforcement hardened against regression. |
| CA-P11 | PASS | Current documentation rebuilt for V3 ownership and immutable authority. |
| CA-P12 | PASS | Persistence cleaned; membership/governance authority is Player-scoped. |
| CA-P13 | PASS | V3 non-visual behavioral suite reconstructed; V2 PHP test compatibility suite removed. |
| CA-P14 | PASS | Final non-visual source leakage scan/certification completed. |

## Final architecture rule

> Identity crosses boundaries. Models do not carry authority across boundaries. Current mutable authority is loaded and validated where the protected operation actually occurs.

Request/authentication code may resolve live Eloquent state while establishing identity. Downstream application contracts receive immutable references/snapshots, scalar IDs, enums, commands/results, or other value objects. Eloquent remains valid inside the owning persistence/application implementation when current database state is required.

## Final verification evidence

- V3 architecture verifier: **PASS**
- V3 persistence verifier: **PASS**
- V3 behavior-contract verifier: **PASS**
- V3 final-source verifier: **PASS**
- Full PHP syntax scan: **PASS**
- Project import-resolution scan: **0 missing project imports**
- `git diff --check`: **PASS**
- V2 PHP tests remaining: **0**
- Active PHPUnit suite: **`tests/v3`**
- Deferred visual spec changed: **NO**

Detailed phase certifications are under `docs/architecture/rewrite-status/`.

## Explicitly deferred

### Visual/Playwright rewrite

Visual coverage was excluded by direction and requires a separate rewrite. `tests/v2/Visual/ApplicationShellV2.spec.ts` is intentionally untouched; `playwright.config.ts` still targets that deferred visual surface.

### GitHub workflow / PR-template migration

CI workflow cleanup was deferred by direction. Existing `.github` V2 workflow references were not modified. They are not evidence against the application rewrite, but they **must be migrated separately** because the V2 PHP test suite has been removed and PHPUnit is now V3-only.

## Runtime execution disposition

The supplied archive has no installed `vendor/autoload.php`; Composer is not installed in the execution image; outbound DNS/network access is unavailable, so dependencies could not be restored. Laravel/PHPUnit, Pint and Larastan therefore could not be executed here.

This limitation is explicitly dispositioned rather than reported as green. Before merge, run the V3 PHPUnit suite and normal PHP static/format checks in a dependency-enabled environment.

## Source blockers

**NONE.**
