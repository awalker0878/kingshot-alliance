# Production Launch Security Review

## Scope

This review covers the post-Phase-6 production-hardening changes only. It does not expand product scope or approve infrastructure controls that cannot be verified from the repository.

## Security controls hardened

- Production launch readiness fails when fewer than two active platform administrators exist.
- Every active platform administrator must use a verified account with confirmed MFA.
- Production runtime configuration is revalidated at launch rather than relying only on CI/staging validation.
- Launch health includes transactional-outbox backlog, failed queue jobs, and recent webhook failure thresholds.
- Active alliances must have platform settings before launch approval.
- The operator launch script requires an owner-readable environment file and preserves the `/platform` unauthenticated authentication boundary check.
- Launch documentation explicitly treats webhook DNS rebinding/egress containment as an infrastructure responsibility and does not claim endpoint validation alone solves SSRF.

## Residual risks requiring external controls

1. Webhook egress must be constrained at the network layer.
2. Production secrets, application-key recovery material, database credentials, and object-storage credentials must remain outside the repository.
3. Alert routing and incident escalation must be tested in the deployed environment.
4. TLS termination and trusted-proxy configuration must be validated against the actual ingress path.
5. Backup recovery must include database, private media, and application-key material as one tested recovery set.

## Decision

No new critical/high application-security risk is introduced by the hardening changes. Production remains a no-go until the external controls recorded in `docs/product/PRODUCTION_LAUNCH_APPROVAL.md` are evidenced and approved by accountable operators.
