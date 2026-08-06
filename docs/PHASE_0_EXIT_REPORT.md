# Phase 0 Exit Report

**Phase:** Engineering Foundation  
**Status:** Implementation candidate — external validation blocked  
**Branch:** `agent/phase-0-engineering-foundation`

## Delivered

- Laravel 13, PHP 8.5, Inertia, Vue, TypeScript, PostgreSQL, and Redis foundation
- Docker local environment and one immutable multi-role runtime image
- App, unprivileged web, worker, scheduler, release, database, and cache services
- Required-variable, hosted architecture, release-provenance, session-security, private-storage, worker-bound, hosted-transport, and production transport validation
- HTTPS and secure-cookie enforcement for hosted staging, with an explicit CI-only loopback exception
- Explicit trusted-proxy handling with separate approval for trust-all configurations
- Structured logs, request IDs, W3C trace propagation with local span identity, stateless health checks, and privacy-preserving request metrics
- Security and correlation headers on normal and rendered error responses
- Production HSTS and non-cacheable liveness/readiness responses
- CI for PHP, frontend, containers, dependency review, CodeQL, and image scanning
- Every external GitHub Action pinned to a verified commit SHA with CI regression enforcement
- Ephemeral staging, migration, health, backup, destructive restore, and post-restore gates
- Digest-only deployment and rollback controls with exact runtime image, version, and release-SHA verification
- OCI source, revision, version, and license metadata on the runtime image
- Schema-state-based pre-migration backup, including stopped or unhealthy previous application containers
- Checksummed backup and fail-closed restore controls with owner-only files and manifest-last completion semantics
- Idempotent post-restore recreation of app, web, worker, and scheduler containers
- Source-control and image-build exclusions for secrets, backups, credentials, keys, and runtime data
- Image-owned package manifests with no persistent or cross-release `bootstrap/cache` state
- Targeted production-image copies with build and development tooling excluded from runtime
- Non-root, read-only staging application roles with all Linux capabilities dropped
- Sanctum package foundation with migrations unpublished and authentication routes disabled until Phase 1
- Pulse dashboard routes and recording disabled until schema and authorization exist
- Horizon workers retained with explicit local/staging/production limits while dashboard and mutation APIs remain denied
- Existing GPL-3.0 repository licensing preserved in source and Composer metadata
- Restricted Nginx PHP execution through the Laravel front controller with version and sensitive request-path disclosure disabled
- Architecture records, security baseline, contribution controls, release controls, issue templates, and operational runbooks

## Validation evidence

- [ ] `composer.lock` and `package-lock.json` committed
- [x] Earlier Composer lock artifact recovered and structurally validated
- [x] Composer lock artifact ZIP and lock-file SHA-256 values recorded in the recovery workflow
- [x] Composer lock `content-hash` exactly matches the current dependency-relevant Composer manifest fields
- [x] Lock generation stages untracked files before comparison, serializes PR/push/manual runs by source branch, and has a controlled artifact-expiry fallback
- [x] Composer dependency resolution and security audit completed in an earlier Actions run
- [x] PostgreSQL startup and baseline migrations completed in an earlier Actions run
- [x] Six Pint findings from that run were corrected
- [x] Latest changed runtime PHP, provider, configuration, and test files pass syntax lint
- [x] Hosted configuration validator logic was exercised independently: secure staging passes, unapproved loopback HTTP fails, explicitly approved loopback CI staging passes, external HTTP still fails, and insecure architecture, storage, worker, session, Pulse, and proxy overrides are rejected
- [x] Sanctum, Pulse, and Horizon Phase 0 route boundaries have regression tests
- [x] Latest deployment, backup, restore, and quality scripts pass `sh -n`
- [x] Latest workflow YAML and Prettier JSON pass local parsing
- [x] Nginx configuration passes local syntax validation
- [x] Mandatory Git and Docker exclusions, targeted copy rules, and immutable action references are enforced by a CI-invoked check
- [x] All external workflow actions are pinned to verified 40-character commits
- [x] The exact GPL-3.0 license blob from `main` is restored and Composer declares `GPL-3.0-only`
- [ ] Laravel tests, Larastan, and final Pint validation
- [ ] ESLint, Prettier, Vue type checking, and Vite build
- [ ] Immutable image build, OCI metadata verification, capability-restricted multi-role staging smoke test
- [ ] Backup and restore demonstration with private file mode, manifest binding, provenance, and post-restore image evidence
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
43. Hosted startup accepted placeholder application versions and release SHAs.
44. Runtime environment overrides could disagree with the immutable image's OCI version and revision while deployment still succeeded.
45. Pre-migration backup depended on a running application container, so a populated database could be migrated without backup after an application crash or manual stop.
46. A backup manifest could be published before its archive, leaving a misleading completion marker during an interrupted rename sequence.
47. Restore did not prove that its manifest named the selected archive or contained exactly one filename and checksum entry.
48. Restore could stop application services before discovering that PostgreSQL was unavailable.
49. Application metrics and Nginx access logs could record sensitive unmatched paths or future route tokens.
50. Hosted startup permitted invalid application keys, non-PostgreSQL databases, non-Redis cache/queue/session backends, unencrypted session payloads, weak SameSite settings, and unapproved trust-all proxy configurations.
51. Horizon's dashboard and mutation APIs could be authorized in non-local environments by package-default Sentinel behavior before an operator identity model existed.
52. Horizon had no explicit staging supervisor configuration and hosted worker counts were not bounded.
53. Pulse dashboard routes and recording could be enabled before its schema and access policy existed.
54. Hosted configuration could select the public filesystem as the default or select S3 without a bucket.
55. A trust-all proxy wildcard could be mixed with explicit proxy addresses, creating ambiguous trust behavior.
56. GitHub Actions workflows referenced mutable release tags instead of reviewed immutable action commits.
57. Externally reachable staging could use HTTP and insecure cookies because transport controls were production-only.
58. A loopback-looking `APP_URL` alone could activate the insecure staging exception without explicit approval.
59. Secure staging did not force HTTPS URL generation from the validated hosted URL.
60. Restore used `compose start`, so a successful database import could fail to recover service availability when runtime containers did not already exist.
61. Pull-request and branch-push lock workflows used different concurrency keys and could race while committing identical lockfiles.

## External validation state

GitHub's official status API reports an active major partial outage on August 6, 2026. Capacity remains constrained; jobs may be delayed, fail, or time out, and webhook delivery may be delayed. Current lock generation is accepted and serialized by source branch but remains queued without runner steps.

No green status is inferred from missing, pending, queued, cancelled, or absent runs. The outage prevents the latest head from supplying final PHP, frontend, container, recovery, CodeQL, dependency-review, or image-scan evidence.

The Composer lock artifact remains available until August 9, 2026. The lock workflow verifies and uses it while available; if it is unavailable, Composer regenerates the lock from the reviewed constraints without executing package scripts.

The lockfile workflow commits through `GITHUB_TOKEN`. After both locks are committed, a repository-owner commit must remove the temporary workflow and trigger the final validation suite against the locked head. Any approval-required runs must be explicitly approved and completed before acceptance.

## Exit criteria

| Criterion | Status | Required evidence |
|---|---|---|
| New developer can build and run the application | Pending | Clean-machine setup using committed lockfiles |
| Required CI passes | Pending | All pull-request checks green on the locked head commit |
| Staging deploys repeatedly from one immutable image | Pending | Accepted image ID/digest, OCI revision/version evidence, and deployment record |
| Backup and restore work against staging data | Pending | Successful recovery job, checksum, private file modes, manifest binding, provenance, and restore record |
| No unapproved shortcut or hidden global state | Ready for review | Architecture and code review |
| Main branch protection is enforceable | Pending | Applied settings using the stable documented check names |

## Decision

Do not begin Phase 1 until the lockfiles are committed, all checks pass on the locked head, staging and recovery evidence is recorded, branch protection is applied, and this report is updated to **Accepted**.
