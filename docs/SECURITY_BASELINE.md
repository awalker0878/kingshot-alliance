# Security Baseline

## Application

- HTTPS is mandatory outside local development.
- Production startup fails when debugging is enabled, `APP_URL` is not HTTPS, secure session cookies are disabled, or PostgreSQL permits plaintext fallback.
- Production responses include HTTP Strict Transport Security; health responses are explicitly non-cacheable.
- Sessions are encrypted, HTTP-only, and same-site restricted.
- State-changing browser requests use CSRF protection.
- Responses include clickjacking, content-sniffing, referrer, permissions, and opener controls, including rendered error responses.
- Content Security Policy is enabled after deployment-specific asset origins are approved.
- Trusted proxy addresses are configured explicitly through `TRUSTED_PROXIES`; trust-all is permitted only when the application service is unreachable except through a controlled internal ingress.
- API and authentication routes use named rate limits.
- Privileged changes require authorization, confirmation, and audit.
- Error responses do not expose stack traces in staging or production.

## Identity and access

Identity, MFA, alliance roles, invitations, and audit implementation are Phase 1. The foundation reserves Sanctum and Laravel authorization mechanisms but does not create user or role tables early.

## Secrets

- Secrets are injected at runtime and never committed.
- Production secrets use a managed secret store.
- Rotation ownership and expiry are documented.
- Logs, exception context, CI output, and support exports must redact secrets.
- Git and Docker exclusions prevent deployment environments, Composer credentials, backups, runtime keys, and `storage/app` data from entering commits or image build contexts.
- `bin/check` fails when mandatory secret and data exclusions are removed.

## Data

- PostgreSQL connections require encryption in hosted production environments.
- Backups are access controlled, compressed only after a successful database dump, recorded in a SHA-256 manifest, verified before restore, and tested through destructive recovery exercises.
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
- Production images are scanned for high and critical vulnerabilities.
- Release images are immutable and identified by digest and source SHA.
- Build contexts exclude development credentials, local data, test output, documentation, and deployment configuration.

## Operations

- Request IDs and W3C trace IDs correlate logs and are returned on successful and rendered error responses.
- Invalid trace context, including all-zero trace or parent identifiers, is discarded and replaced.
- Health endpoints separate liveness from dependency readiness.
- Runtime containers do not mutate shared Laravel caches concurrently; cache clearing occurs once through the controlled release job.
- Production debugging is disabled.
- Incident response follows `docs/runbooks/incident-response.md`.
