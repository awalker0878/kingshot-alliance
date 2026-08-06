# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — external validation blocked  
**Branch:** `agent/phase-0-engineering-foundation`

## Delivered

- Laravel 13, PHP 8.5, Inertia, Vue, TypeScript, PostgreSQL, and Redis foundation
- Docker local environment and one immutable multi-role runtime image
- App, unprivileged web, worker, scheduler, release, database, and cache services
- Required-variable and production security configuration validation
- Explicit trusted-proxy handling
- Structured logs, request IDs, W3C trace propagation, health checks, and request metrics
- Security and correlation headers on normal and rendered error responses
- Production HSTS and non-cacheable liveness/readiness responses
- CI for PHP, frontend, containers, dependency review, CodeQL, and image scanning
- Ephemeral staging, migration, health, backup, destructive restore, and post-restore gates
- Digest-only deployment and rollback controls
- Checksummed backup and fail-closed restore controls
- Source-control and image-build exclusions for secrets, backups, credentials, keys, and runtime data
- Image-owned package manifests with no persistent or cross-release `bootstrap/cache` state
- Sanctum package foundation with migrations unpublished and authentication routes disabled until Phase 1
- Architecture records, security baseline, contribution controls, release controls, and runbooks

## Validation evidence

- [ ] `composer.lock` and `package-lock.json` committed
- [x] Earlier Composer lock artifact recovered and structurally validated
- [x] Composer lock artifact ZIP and lock-file SHA-256 values recorded in the recovery workflow
- [x] Composer dependency constraints are unchanged from the recovered artifact
- [x] Composer dependency resolution and security audit completed in an earlier Actions run
- [x] PostgreSQL startup and baseline migrations completed in an earlier Actions run
- [x] Six Pint findings from that run were corrected
- [x] Latest changed runtime PHP and test files pass syntax lint
- [x] Sanctum configuration and Phase 0 route-boundary test pass PHP syntax lint
- [x] Latest changed deployment, restore, entrypoint, and quality scripts pass `sh -n`
- [x] Latest workflow YAML and Prettier JSON pass local parsing
- [x] Mandatory Git and Docker exclusions are enforced by a dedicated CI-invoked check
- [ ] Laravel tests, Larastan, and final Pint validation
- [ ] ESLint, Prettier, Vue type checking, and Vite build
- [ ] Immutable image build and multi-role staging smoke test
- [ ] Backup and restore demonstration
- [ ] Dependency review, CodeQL, and image vulnerability scan

## Findings corrected during the gate

1. Dependency and test-runner version incompatibility.
2. A scheduled task that depended on a Phase 1 table.
3. Build-time runtime-secret validation.
4. Development container permissions and Composer availability.
5. Invalid GitHub Action version references.
6. Unlocked Composer and npm installation paths.
7. Placeholder deployment and rollback scripts.
8. Source-mounted staging web service.
9. Unverified and pipeline-masked backup and restore behavior.
10. Ignored trusted-proxy configuration.
11. Invalid all-zero W3C trace identifiers.
12. Missing correlation and security headers on rendered errors.
13. Concurrent Laravel cache mutation by production runtime services.
14. Production configuration that could permit debugging, HTTP URLs, insecure cookies, or plaintext PostgreSQL fallback.
15. Missing production HSTS and cacheable health responses.
16. Missing Tailwind CSS 4 stylesheet configuration for the Prettier plugin.
17. Unstable or stale branch-protection check names.
18. Workflow backlog amplification from missing CodeQL and dependency-review concurrency controls.
19. Repeated Composer dependency resolution despite an existing verified lock artifact.
20. Docker build contexts that could include backups, deployment secrets, Composer credentials, runtime keys, or `storage/app` data.
21. Missing source-control exclusions for generated backups and the real staging environment file.
22. A persistent shared `bootstrap/cache` volume that hid the manifest baked into immutable images and could leak cache state across releases and rollbacks.
23. Writable runtime storage granted unnecessarily to the web-only container.
24. Sanctum's CSRF-cookie route exposed before the Phase 1 authentication surface was authorized.

## External blocker

GitHub reported a major Actions outage on August 6, 2026. At 17:02 UTC, GitHub reported that workflow runs were still failing or delayed in starting, queued jobs could time out, and some Actions API requests were returning errors. The latest Phase 0 head has not received workflow runs or status checks.

This is not acceptance evidence. Every pending check must execute successfully after service recovery.

The lockfile workflow commits through `GITHUB_TOKEN`. GitHub may place follow-on pull-request workflows in an approval-required state. Those runs must be explicitly approved and completed against the locked head commit before acceptance.

## Exit criteria

| Criterion | Status | Required evidence |
|---|---|---|
| New developer can build and run the application | Pending | Clean-machine setup using committed lockfiles |
| Required CI passes | Pending | All pull-request checks green on the locked head commit |
| Staging deploys repeatedly from one immutable image | Pending | Accepted image digest and deployment record |
| Backup and restore work against staging data | Pending | Successful recovery job and restore record |
| No unapproved shortcut or hidden global state | Ready for review | Architecture and code review |
| Main branch protection is enforceable | Pending | Applied settings using the stable documented check names |

## Decision

Do not begin Phase 1 until the lockfiles are committed, all checks pass on the locked head, staging and recovery evidence is recorded, branch protection is applied, and this report is updated to **Accepted**.
