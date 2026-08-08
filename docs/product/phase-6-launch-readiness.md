# Phase 6 launch readiness review

## Launch controls

The application is ready to leave Phase 6 only when the final PR head passes PostgreSQL migrations, formatting/static analysis, the full backend/frontend suite, tenant isolation, dependency review, CodeQL, immutable production-image build, ephemeral staging deployment, backup/restore demonstration, and image scan.

## Required operational readiness

Before a real production launch:

- identify at least two operational accounts eligible for platform-admin access; keep grants minimal and require MFA;
- configure HTTPS and trusted proxies correctly;
- configure Redis/Horizon queue capacity with integrations isolated from core work;
- configure egress policy that prevents webhook workers from reaching metadata/private/management networks;
- validate PostgreSQL backup, private-media backup, and application-key recovery together;
- confirm scheduler singleton behavior and queue workers for default/notifications/integrations/maintenance;
- establish alerts for readiness failure, failed jobs, persistent outbox backlog, webhook failure rate, database saturation, and storage capacity;
- test a controlled tenant suspension/restoration and account-deletion legal-hold block;
- test API credential creation/revocation and signed webhook verification;
- review plan limits against expected initial tenant sizes;
- rehearse `PHASE_6_DISASTER_RECOVERY_EXERCISE.md` and record evidence.

## Accepted design decisions

- Platform administrators are a separate cross-tenant grant, not an alliance role.
- MFA and recent password confirmation are mandatory for platform web operations.
- Alliance deletion is logical/recoverable through the retention window.
- Plan/entitlement modeling is present without payment processing.
- API credentials are read-only scoped in Phase 6.
- Webhook processing is bounded and queue-isolated.
- Account deletion anonymizes retained business/audit history rather than destroying referential integrity.
- Support impersonation is not approved and therefore is not implemented.

## Risks that require infrastructure/process controls

Application endpoint validation cannot alone prevent DNS-rebinding SSRF; production egress restrictions are mandatory. Tenant-complete synchronous export has a 100 MiB safety bound; significantly larger tenants should move to an asynchronous object-storage export in a separately approved phase/change. Usage snapshots are operational evidence, not a financial metering ledger. Commercial billing and payment-state enforcement are intentionally outside Phase 6.

## Go/no-go

Go requires protected CI green on the exact release commit plus recorded ownership for remaining infrastructure/process controls. Any bypass of MFA, tenant lifecycle checks, webhook egress policy, backup/key recovery, or protected release gates is a no-go condition.
