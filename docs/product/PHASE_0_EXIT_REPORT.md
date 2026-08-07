# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Accepted  
**Accepted:** 2026-08-07 UTC  
**Branch:** `agent/phase-0-engineering-foundation`

## Decision

Phase 0 is **Accepted** against the five exit criteria approved in `docs/product/IMPLEMENTATION_PLAN.md`. Phase 1 may begin after PR #2 is merged to `main`.

Phase 0 establishes the engineering and operational foundation only. It intentionally excludes Phase 1 identity and alliance-domain capabilities.

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
- CI for PHP, frontend, supported CodeQL analysis, locked dependency auditing, container/staging/recovery validation, and image scanning.
- All external GitHub Actions pinned to reviewed commit SHAs with a regression guard against mutable references.
- Digest-only deployment and rollback controls with exact runtime image/version/release verification.
- Atomically published, checksummed, owner-only backups and fail-closed destructive restore controls.
- Source-control and image-build exclusions for secrets, credentials, backups, deployment environments, runtime data, and development-only files.
- GPL-3.0 repository licensing preserved and aligned with Composer and OCI metadata.
- ADRs, security baseline, contribution controls, branch-protection recommendations, release controls, issue templates, and operational runbooks.

## Validation evidence

### Locked dependencies

- [x] `composer.lock` committed by GitHub Actions in `5e98c9b2f7ec97e993af58c5124a2e4848d71d34`.
- [x] `package-lock.json` committed by the same locked-dependency commit; npm lockfile format is version 3.
- [x] Temporary lock-generation workflow removed in repository-owner commit `ea095942d967953bf3a355fec654f0fd34c74d41`.
- [x] Composer and npm installs use committed lockfiles in CI and supported build paths.

### Final automated acceptance baseline

Validated source head: `9b9e525cabac831ba62601e9847bf8e0168183c1`.

- [x] **Dependency Review** run `31142532395` completed successfully using locked Composer/npm validation and security audits.
- [x] **CodeQL** run `31142532453` completed successfully for JavaScript/TypeScript.
- [x] **CI** run `31142532578` completed successfully.
  - PHP: locked Composer install/audit, PostgreSQL migrations, Pint, Larastan/PHPStan, and parallel PHPUnit.
  - Frontend: locked npm install/audit, ESLint, Prettier, Vue/TypeScript checking, and Vite production build.
  - Container/staging/recovery: build-context guards, PHP 8.5 production image build, OCI metadata, staging Compose, migrations, liveness/readiness, exact runtime-role image identity, private backup, destructive restore, post-restore health/image identity, and Trivy HIGH/CRITICAL scan.
- [x] PHP 8.5 OPcache is validated through its built-in API rather than a removed `opcache.so` artifact.

### Clean-machine and staging evidence

- [x] Fresh GitHub-hosted runners checked out the repository and installed, built, and tested from committed Composer/npm locks.
- [x] The production image was built from the validated source revision with OCI source/version/revision/license metadata.
- [x] The same immutable image identity was verified across staging app, web, worker, and scheduler roles.
- [x] Staging dependencies, migrations, application roles, liveness, and readiness checks completed successfully.
- [x] Production deployment tooling remains stricter than the ephemeral CI demonstration by requiring registry digest references for external deployments and rollbacks.

### Recovery evidence

- [x] Staging backup archive and manifest were created with private permissions.
- [x] Backup checksum, exact manifest binding, source release provenance, and image reference were verified.
- [x] Destructive database restore completed successfully.
- [x] Application roles were recreated and passed post-restore readiness and exact image-identity checks.

### Security and phase-boundary evidence

- [x] Git/Docker exclusions, targeted runtime copies, local loopback bindings, and immutable Action references are CI-enforced.
- [x] Hosted configuration is fail-closed for transport, sessions, storage, workers, proxy trust, release identity, and platform dependencies.
- [x] Public health and landing responses exclude dependency-level results and release identifiers.
- [x] Sanctum, Pulse, and Horizon Phase 0 route boundaries have regression coverage.
- [x] The exact GPL-3.0 license from `main` is preserved and Composer declares `GPL-3.0-only`.
- [x] No Phase 1 users, alliances, memberships, roles, permissions, invitations, or personal-access-token domain tables/services were introduced.
- [x] No unresolved pull-request review threads remain.

## Approved exit criteria

| Approved Phase 0 criterion | Status | Evidence |
|---|---|---|
| A new developer can build and run the application from documented steps | **Passed** | Fresh hosted runners installed and built from committed locks; local Docker workflow is documented. |
| CI is required and passes on a representative pull request | **Passed** | Dependency Review `31142532395`, CodeQL `31142532453`, CI `31142532578`. |
| Staging can be deployed repeatably from a tagged build | **Passed** | OCI metadata and exact image identity were verified across all staging runtime roles. |
| Backup and restore have been demonstrated against staging data | **Passed** | Private backup, checksum/manifest/provenance validation, destructive restore, and post-restore identity checks passed. |
| No product domain depends on unapproved framework shortcuts or hidden global state | **Passed** | Architecture review, CI guards, package/route boundary tests, minimized runtime, and explicit Phase 1 boundary. |

## Gate-review findings

The Phase 0 gate corrected more than sixty implementation, security, reproducibility, recovery, privacy, licensing, container, and CI defects before acceptance. The commit history and PR discussion are the audit trail for individual fixes.

Major categories include dependency/toolchain compatibility, locked builds, PHP 8.5 runtime behavior, immutable release provenance, backup/restore integrity, container least privilege, hosted fail-closed configuration, session/proxy/transport controls, operational dashboard authorization, privacy-safe telemetry, workflow supply-chain pinning, repository-independent dependency auditing, supported CodeQL language configuration, Phase 0/Phase 1 boundaries, GPL licensing, and local-development network exposure controls.

## Repository-hardening follow-up

`docs/operations/BRANCH_PROTECTION.md` contains validated stable check names and recommended repository settings. Enabling GitHub Dependency graph and applying the documented `main` branch-protection/ruleset policy are recommended repository-governance hardening actions before production use, but neither is one of the five approved Phase 0 exit criteria.

## Acceptance

**Phase 0 — Engineering Foundation: ACCEPTED.**

After this report commit passes the required pull-request checks and PR #2 is merged to `main`, proceed to **Phase 1 — Identity and Multi-Tenancy**.
