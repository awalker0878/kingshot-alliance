# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — repository security settings pending  
**Branch:** `agent/phase-0-engineering-foundation`

## Decision

Phase 0 is **not yet Accepted**. The engineering implementation, locked builds, automated quality checks, staging deployment, recovery demonstration, and image scanning have passed on the validated implementation head.

Two GitHub repository-security gates remain before acceptance:

1. Enable **Dependency graph**, then require the restored GitHub dependency-diff action to pass.
2. Apply and record the documented `main` branch-protection settings.

Phase 1 must not begin until those gates are complete, this report is changed to **Accepted**, and PR #2 is merged to `main`.

## Delivered

- Laravel 13 / PHP 8.5 application with Inertia, Vue, TypeScript, Tailwind, PostgreSQL, and Redis.
- Reproducible Composer and npm dependency locks.
- Docker-based local environment with loopback-only published development ports.
- One minimized multi-role runtime image with OCI source/revision/version/license metadata and exact runtime-image verification.
- Non-root, read-only, capability-restricted staging roles for app, web, worker, scheduler, and release jobs.
- Fail-closed hosted configuration validation for release provenance, application keys, PostgreSQL, Redis, encrypted sessions, private storage, Pulse, Horizon, proxy trust, and transport.
- HTTPS and secure-cookie enforcement for hosted staging and production, with an explicit CI-only loopback exception.
- Stateless liveness/readiness endpoints, request IDs, W3C trace continuation, privacy-preserving metrics/logging, HSTS, CSP support, and rendered-error security headers.
- Public landing and health responses restricted to non-sensitive application identity, aggregate health, and request correlation.
- Sanctum authentication routes, Pulse routes/recording, and Horizon dashboard/API access disabled until Phase 1 authorization exists.
- Bounded Horizon worker configuration for local, staging, and production.
- CI for PHP, frontend, CodeQL, GitHub dependency-diff review, package-manager audits, container/staging/recovery validation, and image scanning.
- All external GitHub Actions pinned to reviewed commit SHAs with a regression guard against mutable references.
- Digest-only deployment and rollback controls with exact runtime image/version/release verification.
- Atomically published, checksummed, owner-only backups and fail-closed destructive restore controls.
- Source-control and image-build exclusions for secrets, credentials, backups, deployment environments, runtime data, and development-only files.
- GPL-3.0 repository licensing preserved and aligned with Composer and OCI metadata.
- ADRs, security baseline, contribution controls, branch-protection requirements, release controls, issue templates, and operational runbooks.

## Validation evidence

### Locked dependencies

- [x] `composer.lock` committed by GitHub Actions in `5e98c9b2f7ec97e993af58c5124a2e4848d71d34`.
- [x] `package-lock.json` committed by the same locked-dependency commit; npm lockfile format is version 3.
- [x] Temporary lock-generation workflow removed in repository-owner commit `ea095942d967953bf3a355fec654f0fd34c74d41`.
- [x] Composer and npm installs use their committed lockfiles in CI and supported build paths.

### Automated implementation baseline

Validated implementation head: `b9632edaed606cfae9f6ec18790f02a99ee658c3`.

- [x] CI run `31141660754` completed successfully.
  - PHP: Composer audit, PostgreSQL migrations, Pint, Larastan/PHPStan, and parallel PHPUnit.
  - Frontend: locked npm install/audit, ESLint, Prettier, Vue/TypeScript checking, and Vite production build.
  - Container/staging/recovery: build-context guards, production image build, OCI metadata, staging Compose, migrations, liveness/readiness, exact runtime-role image identity, private backup, destructive restore, post-restore health/image identity, and Trivy HIGH/CRITICAL scan.
- [x] CodeQL completed successfully on the same implementation baseline.
- [x] Composer and npm security audits completed successfully on the same implementation baseline.
- [x] PHP 8.5 OPcache behavior is handled as built-in runtime support rather than a removed `opcache.so` build artifact.
- [ ] GitHub dependency-diff review: the v5.0.0 action is pinned at `a1d282b36b6f3519aa1f3fc636f609c47dddb294` with `fail-on-severity: high`, but run `31142144279` proved the repository's **Dependency graph is disabled**. Enable Dependency graph, then rerun this check to green.

### Clean-machine and staging evidence

- [x] Fresh GitHub-hosted runners checked out the repository and installed, built, and tested from committed Composer/npm locks.
- [x] The production image was built from the validated source revision with OCI source/version/revision/license metadata.
- [x] The same local immutable image ID was verified across staging app, web, worker, and scheduler roles.
- [x] Staging dependencies, migrations, application roles, liveness, and readiness checks completed successfully.
- [x] Production deployment tooling remains stricter than the ephemeral CI demonstration by requiring registry digest references for external deployments and rollbacks.

### Recovery evidence

- [x] Staging backup archive and manifest were created with mode `600`.
- [x] Backup checksum, exact manifest binding, source release provenance, and image reference were verified.
- [x] Destructive database restore completed successfully.
- [x] Application roles were recreated and passed post-restore readiness and exact image-identity checks.

### Security and phase-boundary evidence

- [x] Git/Docker exclusions, targeted runtime copies, local loopback bindings, and immutable Action references are CI-enforced.
- [x] Hosted configuration policy is fail-closed for transport, sessions, storage, workers, proxy trust, release identity, and platform dependencies.
- [x] Public health and landing responses exclude dependency-level results and release identifiers.
- [x] Sanctum, Pulse, and Horizon Phase 0 route boundaries have regression coverage.
- [x] The exact GPL-3.0 license from `main` is preserved and Composer declares `GPL-3.0-only`.
- [x] No Phase 1 users, alliances, memberships, roles, permissions, invitations, or personal-access-token domain tables/services were introduced.
- [x] No unresolved pull-request review threads remain.

## Exit criteria

| Phase 0 criterion | Status | Evidence / remaining action |
|---|---|---|
| New developer can build and run the application | **Passed** | Fresh hosted runners installed and built from committed locks; local Docker workflow is documented. |
| Required automated CI passes | **Pending repository setting** | PHP/frontend/container/recovery/CodeQL are proven green. Enable Dependency graph, then pass the GitHub dependency-diff action. |
| Staging deploys repeatably from one immutable image | **Passed** | OCI metadata and exact image identity were verified across all staging runtime roles. |
| Backup and restore work against staging data | **Passed** | Private backup, checksum/manifest/provenance validation, destructive restore, and post-restore identity checks passed. |
| No unapproved shortcut or hidden global state | **Passed** | Architecture review, CI guards, package/route boundary tests, minimized runtime, and explicit Phase 1 boundary. |
| `main` branch protection is applied | **Pending repository setting** | Apply and record the settings in `docs/BRANCH_PROTECTION.md`, including required checks and PR/review controls. |

## Gate-review findings

The Phase 0 gate has corrected **64** implementation, security, reproducibility, recovery, privacy, licensing, container, and CI defects before acceptance. Finding 64 identified that the check named `Dependency review` performed package-manager audits only; the real GitHub dependency-diff action was restored and then correctly exposed the disabled repository Dependency graph setting.

Major categories include dependency/toolchain compatibility, locked builds, PHP 8.5 runtime behavior, immutable release provenance, backup/restore integrity, container least privilege, hosted fail-closed configuration, session/proxy/transport controls, operational dashboard authorization, privacy-safe telemetry, workflow supply-chain pinning, dependency-diff review, Phase 0/Phase 1 boundaries, GPL licensing, and local network exposure controls.

## Repository security gates

### Dependency graph

Enable GitHub **Dependency graph** for `awalker0878/kingshot-alliance`. Run `31142144279` failed at the dependency-review action with: `Dependency review is not supported on this repository. Please ensure that Dependency graph is enabled.` Once enabled, rerun the `Dependency review` check and require it to pass.

### `main` branch protection

`docs/BRANCH_PROTECTION.md` requires the applied `main` settings and successful stable check contexts to be recorded before acceptance. The connected GitHub integration available to this work can inspect branches, commits, checks, and repository permissions but does not expose Dependency-graph, branch-protection, or ruleset mutation. These repository settings therefore remain explicit administrative gates.

Required stable checks:

- `PHP quality and tests`
- `Frontend quality and build`
- `Container, staging, and recovery`
- `Dependency review`
- `CodeQL (javascript-typescript)`

The protection policy also requires a pull request, at least one approval, stale-approval dismissal, conversation resolution, up-to-date branches, blocked force pushes/deletion, restricted direct pushes, and linear history or squash merges.

## Acceptance

**Phase 0 — Engineering Foundation: NOT YET ACCEPTED.**

Accept only after Dependency graph is enabled and the dependency-diff review is green, the documented `main` protection policy is applied and recorded, this report is changed to **Accepted**, and PR #2 is merged to `main`.
