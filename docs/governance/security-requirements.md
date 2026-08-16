# Security requirements

Status: Current baseline

These are system-level requirements. Context-specific invariants remain documented with the owning architecture/capability.

## Identity and authority

- User authentication/account assurance is separate from Player game authority.
- Active Player is required for Player-scoped game behavior.
- Alliance/Kingdom/Operations/Intelligence authority is never aggregated across all Players owned by one User.
- Platform Administrator is a separate User-scoped grant and cannot bypass game-domain authorization.
- Privileged routes use verified identity/MFA/recent password confirmation where the implemented policy requires it.

## Sessions and transport

- production uses HTTPS and secure session cookies;
- hosted session payloads are encrypted and use Redis-backed sessions;
- CSRF protection applies to state-changing browser requests;
- security headers/trusted-proxy behavior are explicit and fail closed in hosted validation;
- production debugging is disabled.

## Authorization and tenancy

- concrete scope is explicit for protected writes;
- mutable authority is revalidated inside transactions when required;
- same-scope relationships use database constraints where appropriate;
- cross-context contracts must not weaken owner authorization or isolation.

## Secrets

- secrets are runtime-injected and excluded from source/image/docs/logs;
- MFA secrets/recovery codes, tokens, signing keys, app keys and credentials are never logged;
- production key custody is part of disaster recovery.

## Storage and data protection

- production PostgreSQL transport is encrypted;
- private media/storage remains private by default;
- production content media uses durable S3-compatible storage;
- retention, deletion/anonymization and legal-hold rules are enforced by their owners;
- historical evidence preserves attribution without exposing unnecessary private data.

## Asynchronous processing

- durable side effects use transaction-safe intent/outbox patterns where required;
- consumers/deliveries are retry-safe/idempotent;
- diagnostics are bounded and do not leak secret payloads;
- integration queues are operationally isolated enough to prevent retry storms from consuming all core capacity.

## Integrations

- API credentials are scoped and revocable;
- webhook subscriptions/deliveries are scoped, signed and retryable;
- network egress controls must block metadata/private/management targets as required; application URL validation alone is not sufficient infrastructure protection.

## Supply chain and release

- dependency, static/code and container checks run through protected validation;
- release images are immutable and attributable to source revision/version;
- external GitHub Actions should remain pinned to reviewed immutable revisions.

## Production evidence

Code/CI can demonstrate repository controls but cannot prove real ingress, proxy, egress, capacity, alerting, external-service ownership or recovery readiness. Those controls require deployment evidence under [Production approval](production-approval.md).