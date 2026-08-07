# Security Baseline

## Application

- HTTPS and secure session cookies are mandatory for externally reachable hosted environments. The ephemeral CI staging demonstration may use loopback HTTP and insecure cookies only when `APP_URL` resolves to `localhost`, `127.0.0.1`, or `::1` and `ALLOW_INSECURE_LOOPBACK_STAGING=true` is explicitly set.
- Hosted startup requires a valid 32-byte AES-256 application key, a non-placeholder version, and a 40-character lowercase Git release SHA.
- Hosted startup requires PostgreSQL plus Redis-backed cache, queues, and sessions; session payload encryption and `lax` or `strict` SameSite protection cannot be disabled.
- Production startup additionally fails when debugging is enabled, `APP_URL` is not HTTPS, secure session cookies are disabled, or PostgreSQL permits plaintext fallback.
- Production responses include HTTP Strict Transport Security; health responses are explicitly non-cacheable and stateless.
- Public health responses expose only aggregate status and request correlation, never dependency-level results or immutable release metadata.
- Sessions are encrypted, HTTP-only, and same-site restricted.
- State-changing browser requests use CSRF protection.
- Responses include clickjacking, content-sniffing, referrer, permissions, and opener controls, including rendered error responses.
- Content Security Policy is enabled after deployment-specific asset origins are approved.
- Trusted proxy addresses are configured explicitly through `TRUSTED_PROXIES`; `TRUSTED_PROXIES=*` must be the only proxy entry, also requires `ALLOW_TRUST_ALL_PROXIES=true`, and is permitted only behind a controlled internal ingress.
- Nginx routes dynamic requests only through Laravel's `/index.php` front controller, rejects other PHP paths, suppresses server-version disclosure, and excludes URI paths, query strings, referrers, and forwarded-address chains from access logs.
- Application request metrics record named routes or the constant `unmatched`, never unclassified request paths.
- API and authentication routes use named rate limits.
- Privileged changes require authorization, confirmation, and audit.
- Error responses do not expose stack traces in staging or production.

## Identity and access

- One global user identity may belong to multiple alliances; identity rows are not duplicated per tenant.
- Registration canonicalizes email addresses before uniqueness validation and supports open or invitation-only mode.
- Registration requires email verification before alliance mutation routes become available.
- Login regenerates the session identifier, uses a generic invalid-credential response, and is throttled by normalized email plus source IP.
- Logout invalidates the session and rotates the CSRF token.
- Password-reset requests do not reveal whether an account exists. Password reset and password change revoke personal access tokens; password changes also invalidate other authenticated sessions through Laravel's session-authentication mechanism.
- Profile email changes clear verification and require the new address to be verified.
- Authenticated routes use `auth.session` so password-hash changes invalidate stale sessions.
- Active alliance context is explicit session state and is revalidated against an active membership on every tenant-scoped request. Missing context fails closed; stale or suspended membership clears the saved context.
- Alliance activation resolves the target through the user's active memberships rather than trusting global route-model binding.
- Alliance-scoped authorization checks both user membership and tenant-scoped role permissions.
- `membership_roles` carries composite tenant foreign keys so PostgreSQL rejects assigning a role from one alliance to a membership in another alliance even if application code is defective.
- Membership administration enforces role hierarchy and last-owner safety. Role assignment is allowed only to active memberships; leaving or removing a member strips role assignments so later reactivation cannot restore hidden privilege.
- Invitation bearer tokens are high-entropy values stored only as hashes, bound to the intended email address, expire, are one-time use, and rotate on resend. New invitation issuance is serialized per alliance, supersedes earlier pending tokens for the same email, and records explicit audit/outbox revocation evidence. Acceptance/resend/revoke use transactional row locks.
- Invitation, membership, role, and leave operations require a verified account plus recent password confirmation in addition to tenant authorization.
- MFA uses RFC 6238 TOTP. Secrets are stored through encrypted model casts and excluded from serialization.
- MFA recovery codes are stored only as SHA-256 hashes, shown only when created/regenerated, and consumed once.
- Confirmed MFA interrupts password login before an authenticated session is established. Successful challenge regenerates the session identifier; challenge attempts are separately rate limited.
- Starting MFA enrollment cannot overwrite an already-confirmed factor. Enrollment, confirmation, recovery-code regeneration, and disable operations require a verified account and recent password confirmation.
- Authentication, recovery, MFA, alliance, invitation, membership, and role transitions write attributable audit records where the operation is security relevant.
- The Phase 1 threat assessment is maintained in `docs/PHASE_1_THREAT_MODEL.md`.

Operational dashboards follow the same boundary. Pulse registers no dashboard route and recording remains disabled until its schema and access policy are introduced. Horizon workers remain available, but the dashboard and mutation APIs remain explicitly denied because Phase 1 introduces alliance identity rather than a platform-operator administration model.

## Tenancy and asynchronous boundaries

- Tenant-owned application queries use an explicit alliance identifier rather than hidden global tenant state.
- Tenant-scoped requests carry an immutable `TenantContextSnapshot` containing the alliance and membership identifiers required to propagate context safely.
- The snapshot is serializable for queued work and provides the canonical tenant prefix for cache keys, storage paths, export paths, and structured log context.
- Tenant storage/export helpers reject unsafe path segments rather than allowing traversal outside the tenant prefix.
- Request middleware attaches the snapshot only after validating active membership and removes it in a `finally` block after the request.
- Meaningful persisted changes write an outbox row within the same database transaction as the domain mutation.
- Outbox publication is at-least-once. Every persisted event has a unique per-event idempotency key that remains stable across publisher retries and is passed to consumers for deduplication; duplicate no-op role assignment emits no additional event.
- PostgreSQL publishers claim work with `FOR UPDATE SKIP LOCKED`, lease through `available_at`, track attempts, record bounded `last_error` diagnostics, and retry with bounded backoff.
- The outbox publisher runs from the scheduler with overlap and single-server protection; a compatible lock path is retained for the SQLite local/test database.

## Secrets

- Secrets are injected at runtime and never committed.
- Production secrets use a managed secret store.
- Deployment environment files must be owner-readable only with mode `400` or `600`.
- Rotation ownership and expiry are documented.
- Logs, exception context, CI output, and support exports must redact secrets.
- Git and Docker exclusions prevent deployment environments, Composer credentials, backups, runtime keys, and `storage/app` data from entering commits or image build contexts.
- `bin/check` fails when mandatory secret and data exclusions are removed.

## Data

- PostgreSQL connections require encryption in hosted production environments.
- Hosted default storage is restricted to the private local disk or a configured S3 disk; the public disk cannot become the default through environment overrides.
- A populated PostgreSQL schema is backed up before migrations even when the previous application container is stopped or unhealthy; only a verified empty schema skips the first-deployment backup.
- Backups are access controlled, compressed only after a successful database dump, recorded in a SHA-256 manifest, verified before restore, and tested through destructive recovery exercises.
- Backup archives, manifests, and restore working files use collision-resistant temporary paths and owner-only permissions.
- The verified archive is published first and its manifest last; the manifest is the completion marker for a restorable pair. Interruption or failure removes incomplete output.
- Backup provenance is derived from the existing application container, including a stopped container, and never substitutes the incoming deployment target as the source release.
- Restore validates exactly one archive name and checksum entry, confirms the manifest names the selected archive, verifies gzip and SHA-256 integrity, and confirms PostgreSQL readiness before stopping application services.
- Restore operations fail closed when their matching manifest is absent or invalid unless an explicit unverified-restore override is approved.
- Generated backups are excluded from source control and Docker image build contexts.
- Object storage defaults to private visibility and fails on write errors.
- Sensitive exports are authorized, tenant-prefixed, time limited, and audited when export domains are introduced.
- Retention and deletion rules are defined with each domain.
- Phase 1 migration and rollback behavior is documented in `docs/PHASE_1_MIGRATION_ROLLBACK.md`; normal application rollback does not automatically reverse database migrations.

## Dependencies and supply chain

- Composer and npm audits run in CI.
- Dependency Review blocks high-severity additions.
- Dependabot monitors Composer, npm, Docker, and GitHub Actions.
- CodeQL analyzes PHP and TypeScript.
- Every external GitHub Action is pinned to a reviewed 40-character commit SHA, with its release version retained as a comment. CI fails if a mutable action tag or branch is introduced.
- Production images are scanned for high and critical vulnerabilities.
- Release images are immutable and identified by digest, local image ID, source SHA, and OCI metadata.
- Deployment rejects missing or placeholder OCI version/revision metadata, verifies GPL-3.0-only license metadata, and fails if runtime `APP_VERSION` or `RELEASE_SHA` overrides differ from the immutable image.
- Deployment and staging checks prove that app, web, worker, and scheduler roles run the expected immutable image ID and release metadata.
- Build contexts exclude development credentials, local data, test output, documentation, and deployment configuration.
- Runtime stages use targeted copies and do not contain Composer, Git, Bash, frontend source, test tooling, deployment files, or unrelated repository content.
- CI fails if a broad `COPY . .` instruction is reintroduced.
- Lockfile generation stages untracked files before comparison, verifies the preserved Composer artifact when available, and regenerates from reviewed constraints only when that artifact is unavailable.

## Operations

- Request IDs and W3C trace IDs correlate logs and are returned on successful and rendered error responses.
- Valid upstream trace IDs and sampling flags are preserved while a new local parent/span ID represents the current request.
- Invalid trace context, including all-zero trace or parent identifiers, is discarded and replaced.
- Phase 1 audit records retain request/trace correlation where an HTTP request is present, plus actor, tenant, subject, and event metadata appropriate to the action.
- Health endpoints separate liveness from dependency readiness, do not start browser sessions, and return no dependency-level or release-identifying data to public callers.
- Local development publishes the application, Vite, PostgreSQL, and Redis only on `127.0.0.1`; CI fails if those default Compose bindings are broadened to all host interfaces.
- `bootstrap/cache` remains image-owned and is not persisted or shared between releases; each digest uses the package manifest built into that image.
- Staging application roles run as non-root, use read-only filesystems, set `no-new-privileges`, and drop all Linux capabilities.
- The web role mounts runtime storage read-only; write access remains limited to application roles that require it.
- Horizon has explicit local, staging, and production supervisor settings. Hosted supervisor counts must remain between 1 and 64 processes.
- Backup manifests record the running release SHA and image reference, and CI validates both before destructive restore.
- Production debugging is disabled.
- Incident response follows `docs/runbooks/incident-response.md`.
