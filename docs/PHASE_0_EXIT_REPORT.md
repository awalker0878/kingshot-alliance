# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — external validation pending  
**Branch:** `agent/phase-0-engineering-foundation`

## Delivered

- Laravel 13, PHP 8.5, Inertia, Vue, TypeScript, PostgreSQL, and Redis foundation
- Docker local environment and one immutable multi-role runtime image
- App, unprivileged web, worker, scheduler, release, database, and cache services
- Required-variable and production security configuration validation
- Explicit trusted-proxy handling
- Structured logs, request IDs, W3C trace propagation with local span identity, stateless health checks, and request metrics
- Security and correlation headers on normal and rendered error responses
- Production HSTS and non-cacheable liveness/readiness responses
- CI for PHP, frontend, containers, dependency review, CodeQL, and image scanning
- Ephemeral staging, migration, health, backup, destructive restore, and post-restore gates
- Digest-only deployment and rollback controls with exact runtime image-ID verification
- OCI source, revision, version, and license metadata on the runtime image
- Checksummed, atomically published backup and fail-closed restore controls with owner-only file permissions
- Source-control and image-build exclusions for secrets, backups, credentials, keys, and runtime data
- Image-owned package manifests with no persistent or cross-release `bootstrap/cache` state
- Targeted production-image copies with build and development tooling excluded from runtime
- Non-root, read-only staging application roles with all Linux capabilities dropped
- Sanctum package foundation with migrations unpublished and authentication routes disabled until Phase 1
- Existing GPL-3.0 repository licensing preserved in source and Composer metadata
- Restricted Nginx PHP execution through the Laravel front controller with version and sensitive query-log disclosure disabled
- Architecture records, security baseline, contribution controls, release controls, issue templates, and operational runbooks

## Validation evidence

- [ ] `composer.lock` and `package-lock.json` committed
- [x] Earlier Composer lock artifact recovered and structurally validated
- [x] Composer lock artifact ZIP and lock-file SHA-256 values recorded in the recovery workflow
- [x] Composer lock `content-hash` exactly matches the current dependency-relevant Composer manifest fields
- [x] Lock generation now stages untracked files before comparison and has a controlled artifact-expiry fallback
- [x] Composer dependency resolution and security audit completed in an earlier Actions run
- [x] PostgreSQL startup and baseline migrations completed in an earlier Actions run
- [x] Six Pint findings from that run were corrected
- [x] Latest changed runtime PHP and test files pass syntax lint
- [x] Sanctum configuration and Phase 0 route-boundary test pass PHP syntax lint
- [x] Latest changed deployment, restore, entrypoint, and quality scripts pass `sh -n`
- [x] Latest workflow YAML and Prettier JSON pass local parsing
- [x] Nginx configuration passes local syntax validation
- [x] Mandatory Git and Docker exclusions and targeted copy rules are enforced by a dedicated CI-invoked check
- [x] The exact GPL-3.0 license blob from `main` is restored and Composer declares `GPL-3.0-only`
- [ ] Laravel tests, Larastan, and final Pint validation
- [ ] ESLint, Prettier, Vue type checking, and Vite build
- [ ] Immutable image build, OCI metadata verification, capability-restricted multi-role staging smoke test
- [ ] Backup and restore demonstration with private file mode, atomic publication, provenance, and post-restore image evidence
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
25. The existing GPL-3.0 license was silently replaced with MIT text and inconsistent Composer metadata.
26. A broad `COPY . .` and shared base tooling caused development files, Composer, Git, Bash, frontend sources, and unrelated repository content to enter the production runtime image.
27. Backup archives, manifests, and restore working files inherited the operator's umask and could be group- or world-readable.
28. Deployment health checks did not prove that every runtime role used the requested immutable image.
29. Runtime images lacked OCI revision and license metadata needed to connect staging evidence to the reviewed commit.
30. PHPStan configuration used an option removed in PHPStan 2, which would fail before static analysis began.
31. Readiness cache-key construction passed a mixed request attribute into string concatenation under level-8 static analysis.
32. Nginx would execute any PHP file placed under `public` and disclosed its version.
33. Nginx access logs recorded query strings and referrers that could contain secrets or tokens.
34. Valid upstream `traceparent` values were echoed with the caller's parent span instead of creating an identity for the local operation.
35. Readiness ran through browser session and Inertia middleware rather than a stateless route boundary.
36. Backup temporary paths were predictable and final archive names could appear before verification completed.
37. Backup and restore signal traps could return from `INT` or `TERM` and resume destructive work.
38. Backup provenance could fall back to the incoming deployment target when inspection of the running source image failed.
39. Staging application roles retained unnecessary Linux capabilities.
40. Deployment accepted environment files with group- or world-readable permissions.
41. The lock generator used `git diff` before staging, so new untracked lockfiles would be mistaken for unchanged files and never committed.
42. Lock generation had no safe fallback after the preserved Composer artifact expired.

## External validation state

GitHub's public status currently reports Actions as operational. The repository's latest pull-request head has nevertheless not received workflow runs yet, while earlier heads were queued or cancelled by the configured concurrency controls. This is a repository event or residual queue condition, not acceptance evidence.

The Composer lock artifact remains available until August 9, 2026. The lock workflow verifies and uses it while available; if it is unavailable, Composer regenerates the lock from the reviewed constraints without executing package scripts.

The lockfile workflow commits through `GITHUB_TOKEN`. GitHub may place follow-on pull-request workflows in an approval-required state. Those runs must be explicitly approved and completed against the locked head commit before acceptance.

## Exit criteria

| Criterion | Status | Required evidence |
|---|---|---|
| New developer can build and run the application | Pending | Clean-machine setup using committed lockfiles |
| Required CI passes | Pending | All pull-request checks green on the locked head commit |
| Staging deploys repeatedly from one immutable image | Pending | Accepted image ID/digest, OCI revision evidence, and deployment record |
| Backup and restore work against staging data | Pending | Successful recovery job, checksum, private file modes, atomic publication, provenance, and restore record |
| No unapproved shortcut or hidden global state | Ready for review | Architecture and code review |
| Main branch protection is enforceable | Pending | Applied settings using the stable documented check names |

## Decision

Do not begin Phase 1 until the lockfiles are committed, all checks pass on the locked head, staging and recovery evidence is recorded, branch protection is applied, and this report is updated to **Accepted**.
