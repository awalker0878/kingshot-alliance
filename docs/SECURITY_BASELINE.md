# Security Baseline

## Application

- HTTPS is mandatory for externally reachable hosted environments. The ephemeral CI staging demonstration may use loopback HTTP only.
- Hosted startup requires a valid 32-byte AES-256 application key, a non-placeholder version, and a 40-character lowercase Git release SHA.
- Hosted startup requires PostgreSQL plus Redis-backed cache, queues, and sessions; session payload encryption and `lax` or `strict` SameSite protection cannot be disabled.
- Production startup additionally fails when debugging is enabled, `APP_URL` is not HTTPS, secure session cookies are disabled, or PostgreSQL permits plaintext fallback.
- Production responses include HTTP Strict Transport Security; health responses are explicitly non-cacheable and stateless.
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

Identity, MFA, alliance roles, invitations, and audit implementation are Phase 1. The foundation reserves Sanctum and Laravel authorization mechanisms but does not create user, token, or role tables early. Sanctum migrations remain unpublished and its CSRF-cookie route is disabled until Phase 1 explicitly enables the authentication surface.

Operational dashboards follow the same boundary. Pulse registers no dashboard route and recording remains disabled until its schema and access policy are introduced. Horizon workers remain available, but the dashboard and mutation APIs are explicitly denied in every environment until Phase 1 provides an authorized operator identity model.

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
- Sensitive exports are authorized, time limited, and audited.
- Retention and deletion rules are defined with each domain.

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
- Health endpoints separate liveness from dependency readiness and do not start browser sessions.
- `bootstrap/cache` remains image-owned and is not persisted or shared between releases; each digest uses the package manifest built into that image.
- Staging application roles run as non-root, use read-only filesystems, set `no-new-privileges`, and drop all Linux capabilities.
- The web role mounts runtime storage read-only; write access remains limited to application roles that require it.
- Horizon has explicit local, staging, and production supervisor settings. Hosted supervisor counts must remain between 1 and 64 processes.
- Backup manifests record the running release SHA and image reference, and CI validates both before destructive restore.
- Production debugging is disabled.
- Incident response follows `docs/runbooks/incident-response.md`.
