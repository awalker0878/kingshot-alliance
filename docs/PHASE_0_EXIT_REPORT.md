# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — final external validation pending  
**Branch:** `agent/phase-0-engineering-foundation`

## Delivered

Phase 0 establishes the engineering and operational foundation only. It intentionally excludes Phase 1 identity and alliance-domain capabilities.

Delivered foundation includes:

- Laravel 13 / PHP 8.5 application with Inertia, Vue, TypeScript, Tailwind, PostgreSQL, and Redis.
- Reproducible Composer and npm dependency locks.
- Local Docker environment and one minimized immutable multi-role runtime image.
- Non-root, read-only, capability-restricted staging roles for app, web, worker, scheduler, and release jobs.
- Fail-closed hosted configuration validation for release provenance, keys, PostgreSQL, Redis, encrypted sessions, storage, Pulse, Horizon, proxy trust, and transport.
- HTTPS and secure-cookie enforcement for hosted staging and production, with an explicit CI-only loopback exception.
- Stateless liveness/readiness endpoints, request IDs, W3C trace continuation, privacy-preserving metrics/logging, HSTS, CSP support, and rendered-error security headers.
- Public landing and health responses restricted to non-sensitive application identity, aggregate health, and request correlation.
- Loopback-only local bindings for application, Vite, PostgreSQL, and Redis ports.
- Sanctum authentication routes, Pulse routes/recording, and Horizon dashboard/API access disabled until Phase 1 authorization exists.
- Bounded Horizon worker configuration for local, staging, and production.
- CI for PHP, frontend, CodeQL, dependency review, container/staging/recovery validation, and image scanning.
- All external GitHub Actions pinned to verified commit SHAs with a regression guard against mutable references.
- Digest-only deployment and rollback with exact runtime image/version/release verification.
- Atomically published, checksummed, owner-only backups and fail-closed destructive restore controls.
- Source-control and image-build exclusions for secrets, credentials, backups, deployment environments, runtime data, and development-only files.
- GPL-3.0 repository licensing preserved and aligned with Composer and OCI metadata.
- ADRs, security baseline, contribution controls, branch-protection guidance, release controls, issue templates, and operational runbooks.

## Validation evidence

- [x] `composer.lock` committed by GitHub Actions in commit `5e98c9b2f7ec97e993af58c5124a2e4848d71d34`.
- [x] `package-lock.json` committed by the same locked-dependency commit; npm lockfile format is version 3.
- [x] Temporary lock-generation workflow removed in repository-owner commit `ea095942d967953bf3a355fec654f0fd34c74d41`.
- [x] Composer lock artifact and lock-file SHA-256 values were verified during generation.
- [x] Composer dependency resolution and security audit completed in an earlier Actions run.
- [x] PostgreSQL startup and baseline migrations completed in an earlier Actions run.
- [x] Latest changed runtime PHP, provider, configuration, and test files pass syntax lint.
- [x] Hosted configuration policy was exercised independently, including secure staging, explicit loopback-only CI exception, external HTTPS enforcement, architecture/storage/worker/session/Pulse/proxy controls.
- [x] Public readiness and landing regression tests exclude dependency-level results and release identifiers.
- [x] Local Compose binds published development ports to `127.0.0.1` with a regression guard.
- [x] Sanctum, Pulse, and Horizon Phase 0 route boundaries have regression coverage.
- [x] Deployment, backup, restore, and quality scripts pass shell syntax validation.
- [x] Workflow YAML, Prettier JSON, Compose YAML, and Nginx configuration pass local parsing/syntax validation.
- [x] Git/Docker exclusions, targeted runtime copies, loopback bindings, and immutable Action references are CI-enforced.
- [x] Exact GPL-3.0 license blob from `main` is restored and Composer declares `GPL-3.0-only`.
- [ ] Laravel tests, Larastan, and final Pint validation on the owner-triggered locked head.
- [ ] ESLint, Prettier, Vue type checking, and Vite build on the owner-triggered locked head.
- [ ] CodeQL and dependency review on the owner-triggered locked head.
- [ ] Immutable image build, OCI metadata verification, capability-restricted staging smoke test, and image vulnerability scan.
- [ ] Backup/restore demonstration with private file modes, manifest binding, provenance, and post-restore image evidence.

## Gate-review findings

The Phase 0 gate has corrected **63 implementation, security, reproducibility, recovery, privacy, licensing, and CI defects** before acceptance. The detailed commit history and PR discussion are the audit trail for individual fixes. Major categories include:

- dependency and toolchain compatibility;
- reproducible locked builds;
- immutable deployment and release provenance;
- backup/restore integrity and crash-state recovery;
- container least privilege and build-context protection;
- hosted configuration fail-closed behavior;
- proxy, transport, session, and application-key security;
- operational dashboard/API authorization boundaries;
- privacy-safe request, health, trace, and infrastructure logging;
- GitHub Actions supply-chain pinning and stable required-check names;
- Phase 0/Phase 1 schema and route boundaries;
- preservation of the repository's GPL-3.0 licensing;
- local-development network exposure controls.

## External validation state

Both lockfiles are now committed and the temporary generator has been removed. The final validation trigger is the repository-owner commit `ea095942d967953bf3a355fec654f0fd34c74d41` plus this evidence update.

GitHub's official status API still reports an active major Actions incident on August 6, 2026. The latest incident update says webhook triggers remain throttled and runners can be assigned jobs that are no longer valid. Missing, pending, queued, cancelled, or absent checks are therefore **not** acceptance evidence.

Do not infer a green gate until the required workflows execute successfully against the current locked owner head.

## Exit criteria

| Criterion | Status | Required evidence |
|---|---|---|
| New developer can build and run the application | Pending | Clean-machine setup using committed lockfiles |
| Required CI passes | Pending | All required checks green on the locked owner head |
| Staging deploys repeatedly from one immutable image | Pending | Accepted image ID/digest, OCI revision/version, and deployment record |
| Backup and restore work against staging data | Pending | Successful recovery job with checksum, file-mode, manifest, provenance, and image evidence |
| No unapproved shortcut or hidden global state | Ready for review | Architecture and code review |
| Main branch protection is enforceable | Pending | Applied settings using documented stable check names |

## Decision

**Do not begin Phase 1 yet.** Phase 0 can be accepted only after the final locked-head checks are green, clean-machine setup and staging/recovery evidence are captured, branch protection is applied, and this report is changed to **Accepted**.
