# Security and Tenancy

Security is a product boundary, not a later hardening step. The canonical control set is [docs/SECURITY_BASELINE.md](../SECURITY_BASELINE.md), with phase-specific threat models under `docs/`.

## Tenant model

Kingshot Alliance uses global user identities with alliance-scoped memberships. A user may belong to multiple alliances, but every alliance-scoped request must resolve and revalidate an active membership for the selected alliance.

Key rules:

- Active alliance context is explicit and fails closed when missing or stale.
- Alliance-scoped authorization checks membership and role permissions.
- Tenant-owned queries receive an explicit alliance identifier.
- Queued work carries a serializable tenant-context snapshot.
- Cache keys, storage paths, exports, structured logs, and asynchronous work preserve tenant context.
- Submitted object identifiers are re-resolved inside the active alliance before privileged mutations.
- Database constraints reinforce application-level tenant isolation where practical.

## Authentication and account protection

- Email addresses are canonicalized before uniqueness checks.
- Email verification is required before alliance mutation routes become available.
- Login is throttled and regenerates the session identifier.
- Password reset does not reveal whether an account exists.
- Password changes revoke tokens and invalidate other authenticated sessions.
- MFA uses TOTP with encrypted secrets and one-time recovery codes stored as hashes.
- Sensitive authentication and alliance-administration transitions are auditable.

## Privileged actions

Privileged mutations require the complete boundary:

1. Authenticated session
2. Verified identity
3. Valid active-alliance membership
4. Required alliance permission
5. Tenant-safe object resolution
6. Recent password confirmation
7. Audit attribution

The integrated Phase 1–4 audit applies this consistently to identity, content, event-coordinator, and recruiter mutations. Read-only management views and normal member self-service actions do not automatically require password reconfirmation.

## Invitations and membership

- Invitation bearer tokens are high entropy and stored only as hashes.
- Invitations are email-bound, expiring, one-time use, and rotate on resend.
- Membership administration enforces role hierarchy and last-owner safety.
- Leaving/removing a member strips role assignments so later reactivation cannot restore hidden privilege.
- Cross-alliance role assignment is constrained at both application and database layers.

## Application and transport controls

Hosted environments require secure sessions and HTTPS except for explicitly allowed loopback-only staging demonstrations. Production disables debugging and adds HSTS. State-changing browser requests use CSRF protection, and responses include browser-security headers.

Public health endpoints reveal aggregate health and correlation identifiers only; they do not expose dependency details or release metadata.

## Data protection

- PostgreSQL transport encryption is required in hosted production.
- Default object storage is private.
- Tenant storage/export helpers reject unsafe path segments.
- Backups are checksummed, verified before restore, and protected by strict file permissions.
- Sensitive exports, when introduced, must be authorized, tenant-prefixed, time-limited, and auditable.
- Domain-specific retention and deletion rules are defined alongside each domain.

Recruitment has an explicit retention/anonymization workflow for declined and withdrawn candidates.

## Supply chain

The CI baseline includes Composer/npm audits, Dependency Review, CodeQL, pinned external GitHub Actions, production-image vulnerability scanning, immutable release metadata, and checks that prevent broad runtime-image copies from reintroducing development-only or sensitive repository content.

## Further reading

- [Security baseline](../SECURITY_BASELINE.md)
- [Phase 1 threat model](../PHASE_1_THREAT_MODEL.md)
- [Phase 2 threat model](../PHASE_2_THREAT_MODEL.md)
- [Phase 3 threat model](../PHASE_3_THREAT_MODEL.md)
- [Phase 4 threat model](../PHASE_4_THREAT_MODEL.md)
- [Phases 1–4 alignment audit](../PHASES_1_4_ALIGNMENT_AUDIT.md)
