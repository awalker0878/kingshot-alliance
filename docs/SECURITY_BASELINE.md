# Security Baseline

## Application

- HTTPS is mandatory outside local development.
- Sessions are encrypted, HTTP-only, and same-site restricted.
- State-changing browser requests use CSRF protection.
- Responses include clickjacking, content-sniffing, referrer, permissions, and opener controls.
- Content Security Policy is enabled after deployment-specific asset origins are approved.
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

## Data

- PostgreSQL connections use encryption in hosted environments.
- Backups are encrypted, access controlled, verified, and tested.
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

## Operations

- Request IDs and trace IDs correlate logs.
- Health endpoints separate liveness from dependency readiness.
- Production debugging is disabled.
- Incident response follows `docs/runbooks/incident-response.md`.
