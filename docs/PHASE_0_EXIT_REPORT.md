# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Accepted  
**Accepted:** 2026-08-07 UTC  
**Branch:** `agent/phase-0-engineering-foundation`

## Decision

Phase 0 is **Accepted** against the approved implementation-plan exit criteria. Phase 1 may begin after this pull request is merged to `main`.

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
- GitHub Actions for PHP, frontend, CodeQL, dependency auditing, container/staging/recovery validation, and image scanning.
- All external GitHub Actions pinned to reviewed commit SHAs with a regression guard against mutable references.
- Digest-only deployment and rollback controls with exact runtime image/version/release verification.
- Atomically published, checksummed, owner-only backups and fail-closed destructive restore controls.
- Source-control and image-build exclusions for secrets, credentials, backups, deployment environments, runtime data, and development-only files.
- GPL-3.0 repository licensing preserved and aligned with Composer and OCI metadata.
- ADRs, security baseline, contribution controls, branch-protection recommendations, release controls, issue templates, and operational runbooks.

## Accepted validation baseline

**Validated source head:** `b9632edaed606cfae9f6ec18790f02a99ee658c3`

### Locked dependencies

- [x] `composer.lock` committed by GitHub Actions in `5e98c9b2f7ec97e993af58c5124a2e4848d71d34`.
- [x] `package-lock.json` committed by the same locked-dependency commit; npm lockfile format is version 3.
- [x] Temporary lock-generation workflow removed in repository-owner commit `ea095942d967953bf3a355fec654f0fd34c74d41`.
- [x] Composer and npm installs use their committed lockfiles in CI and supported build paths.

### Required automated gates

- [x] **Dependency Review** — run `31141660750` completed successfully.
  - Composer manifest validation.
  - Locked Composer security audit.
  - Locked npm install and high-severity npm audit.
- [x] **CodeQL** — run `31141660742` completed successfully for the supported JavaScript/TypeScript language set.
- [x] **CI** — run `31141660754` completed successfully.
  - PHP quality and tests: Composer audit, migrations, Pint, Larastan, and parallel PHPUnit.
  - Frontend quality and build: npm audit, ESLint, Prettier, Vue/TypeScript checking, and Vite production build.
  - Container/staging/recovery: build-context checks, production image build, runtime smoke checks, staging Compose validation, migrations, application startup, health checks, runtime-role identity checks, backup, destructive restore, post-restore health/identity verification, and Trivy vulnerability scan.

### Clean-machine and staging evidence

- [x] A fresh GitHub-hosted runner checked out the repository and installed/build/tested from the committed Composer and npm locks.
- [x] The production image was built from the accepted source revision and tagged for the validated commit.
- [x] OCI source, version, revision, and GPL-3.0 license metadata were verified.
- [x] The same built image identity was verified across the staging app, web, worker, and scheduler roles.
- [x] Staging dependencies, migrations, application roles, liveness, and readiness checks completed successfully.
- [x] The production deployment tooling remains stricter than the CI demonstration by requiring registry digest references for external deployments and rollbacks.

### Recovery evidence

- [x] A staging database backup was created with private file permissions.
- [x] Backup checksum, manifest binding, source release provenance, and image evidence were verified.
- [x] A destructive restore was completed against staging data.
- [x] Application roles were recreated/restarted and passed post-restore readiness and exact image-identity checks.

### Security and phase-boundary evidence

- [x] Git/Docker exclusions, targeted runtime copies, local loopback bindings, and immutable Action references are CI-enforced.
- [x] Hosted configuration policy is fail-closed for transport, sessions, storage, workers, proxy trust, release identity, and platform dependencies.
- [x] Public health and landing responses exclude dependency-level results and release identifiers.
- [x] Sanctum, Pulse, and Horizon Phase 0 route boundaries have regression coverage.
- [x] The exact GPL-3.0 license from `main` is preserved and Composer declares `GPL-3.0-only`.
- [x] No Phase 1 users, alliances, memberships, roles, permissions, invitations, or personal-access-token domain tables/services were introduced.
- [x] No unresolved pull-request review threads remain.

## Exit criteria

| Approved Phase 0 criterion | Status | Evidence |
|---|---|---|
| A new developer can build and run the application from documented steps | **Passed** | Clean hosted runner installed from committed locks; application, migrations, frontend, and production image built successfully; local Docker workflow is documented. |
| CI is required and passes on a representative pull request | **Passed** | Dependency Review `31141660750`, CodeQL `31141660742`, CI `31141660754`. |
| Staging can be deployed repeatably from a tagged build | **Passed** | CI built one tagged image from the accepted revision, verified OCI metadata and exact image identity, and deployed the same image across all staging runtime roles. |
| Backup and restore have been demonstrated against staging data | **Passed** | CI backup, provenance/integrity verification, destructive restore, and post-restore health/image checks all passed. |
| No product domain depends on unapproved framework shortcuts or hidden global state | **Passed** | Architecture review, CI guards, route/package boundary tests, minimized runtime, and explicit Phase 1 boundary. |

## Gate-review findings

The Phase 0 gate corrected more than sixty implementation, security, reproducibility, recovery, privacy, licensing, container, and CI defects before acceptance. The commit history and PR discussion are the audit trail for individual fixes. Major categories include:

- dependency and toolchain compatibility;
- reproducible locked builds;
- PHP 8.5 runtime-extension behavior and container construction;
- immutable deployment and release provenance;
- backup/restore integrity and crash-state recovery;
- container least privilege and build-context protection;
- hosted configuration fail-closed behavior;
- proxy, transport, session, and application-key security;
- operational dashboard/API authorization boundaries;
- privacy-safe request, health, trace, and infrastructure logging;
- GitHub Actions supply-chain pinning and stable required-check names;
- repository-independent dependency auditing;
- supported CodeQL language configuration;
- Phase 0/Phase 1 schema and route boundaries;
- preservation of the repository's GPL-3.0 licensing;
- local-development network exposure controls.

## Governance follow-up

The approved Phase 0 deliverable is a documented branch-protection recommendation, not proof that repository settings were changed. `docs/BRANCH_PROTECTION.md` contains the now-validated stable check names. Applying those repository settings remains a recommended governance action before production use; it is not an additional gate beyond the approved Phase 0 exit criteria.

## Acceptance

**Phase 0 — Engineering Foundation: ACCEPTED.**

After this report commit passes the same required pull-request checks and PR #2 is merged to `main`, work may proceed to **Phase 1 — Identity and Multi-Tenancy**.
