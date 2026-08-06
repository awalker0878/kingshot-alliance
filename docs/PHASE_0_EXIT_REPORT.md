# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — external validation blocked  
**Branch:** `agent/phase-0-engineering-foundation`

## Delivered

- Laravel 13 and PHP 8.5 application scaffold
- Inertia, Vue, TypeScript, Tailwind, and Vite frontend
- PostgreSQL and Redis configuration
- Docker development and immutable runtime-image definitions
- One runtime image capable of app, unprivileged web, worker, scheduler, and release-job roles
- Horizon, Pulse, Pennant, Sanctum, and S3-compatible foundations
- Environment validation for staging and production
- Liveness and dependency-readiness endpoints
- JSON logging, request IDs, W3C trace correlation, and request-duration events
- Security response headers and encrypted session defaults
- PHP, frontend, container, dependency, CodeQL, and vulnerability CI workflows
- Ephemeral staging, health, backup, restore, and post-restore readiness validation in CI
- Baseline migrations and serial/parallel automated test commands
- Architecture records, security guidance, delivery controls, and operational runbooks
- Digest-only deploy and rollback controls
- Checksummed backup and fail-closed restore controls
- Contribution guide, issue templates, pull-request template, CODEOWNERS, and branch-protection recommendations
- Locked dependency installation enforced by CI, Docker, Composer setup, `bin/setup`, and the Makefile
- Current supported GitHub Action majors for checkout, Node setup, CodeQL, and dependency review

## Validation evidence

- [ ] Composer and npm lockfiles committed
- [x] Composer dependency resolution completed in GitHub Actions
- [x] Composer security audit reported no vulnerability advisories
- [x] PostgreSQL service startup and baseline migrations completed in GitHub Actions
- [x] Six Pint formatting findings were identified and corrected
- [x] JSON manifests parse
- [x] YAML workflow and Compose files pass static parsing
- [x] PHP source files pass syntax lint using the available PHP runtime
- [x] All deployment and operational shell scripts pass `sh -n`
- [ ] Laravel runtime tests
- [ ] Larastan and final Pint validation
- [ ] ESLint, Prettier, Vue type check, and Vite build
- [ ] Production container build and multi-role runtime smoke test
- [ ] Ephemeral staging backup and restore demonstration
- [ ] Dependency review, CodeQL, and image vulnerability scans

The unchecked validations require package downloads, hosted runners, or Docker. The implementation environment does not have external package-network or Docker access.

## Findings corrected during the gate

1. Pinned ParaTest 7.20.0 for compatibility with PHPUnit 12.
2. Removed a Sanctum pruning schedule that depended on a Phase 1 table.
3. Moved staging and production configuration validation out of Composer package discovery and into container startup.
4. Corrected container permissions and Composer availability for development workflows.
5. Corrected invalid `actions/checkout@v7` and `actions/setup-node@v7` references to supported v6 releases.
6. Replaced unlocked normal builds with `composer install` plus committed lock validation and `npm ci`.
7. Updated the production Docker stages to require both lockfiles.
8. Removed repeated frontend dependency installation from normal container startup.
9. Replaced placeholder deploy and rollback scripts with executable, digest-only controls.
10. Added an unprivileged Nginx role to the immutable application image so staging does not depend on a source-code bind mount.
11. Added a staging Compose topology with app, web, worker, scheduler, release, PostgreSQL, and Redis roles.
12. Changed backup and restore from an unverified SQL workflow to checksummed manifests and fail-closed integrity validation.
13. Added an ephemeral staging and recovery cycle to the container CI gate.

## External validation blocker

On August 6, 2026, GitHub reported a partial outage for GitHub Actions. Workflow runs may fail to start or fail during execution, and some Actions REST API requests are returning errors. The Phase 0 workflows remain queued without runner assignment during that incident.

This is an external platform blocker, not acceptance evidence. All checks must execute successfully after service recovery.

## Exit criteria

| Criterion | Status | Evidence required |
|---|---|---|
| New developer can build and run from documented steps | Pending | Clean-machine setup demonstration using committed lockfiles |
| Required CI passes on a representative pull request | Pending | All PR checks green after Actions recovery |
| Staging deploys repeatably from an immutable image | Pending | Deployment record containing the accepted image digest |
| Backup and restore demonstrated against staging data | Pending | Successful CI recovery job and retained restore evidence |
| No unapproved shortcut or hidden global state | Ready for review | Architecture and code review |

## Decision

Do not begin Phase 1 until the lockfiles are committed, pending validation is completed, CI findings are fixed, staging and recovery demonstrations are recorded, and this report is updated to **Accepted**.
