# Security documentation

[← Documentation home](../README.md)

This directory owns **repository-wide security policy and program evidence**: the shared security baseline, phase-wide threat history, and production-launch security boundary. Security reviews that primarily protect one code/domain capability live with that owning domain under `docs/domains/<domain>/security/`.

Security documentation supplements—not replaces—authorization in code, tenant-isolation tests, infrastructure controls, operations, and accountable production evidence.

## Start here

- [Security baseline](security-baseline.md) — cross-cutting requirements for authentication, authorization, tenancy, data handling, transport, secrets, dependencies, audit, integrations, and operational security.
- [Production launch security review](production-launch-security-review.md) — repository-controlled launch-security review plus external controls that remain production responsibilities.
- [Production launch approval](../product/production-launch-approval.md) — authoritative real-production go/no-go record.
- [Kingdoms security evidence](../domains/kingdoms/security/README.md) — `KINGDOMS-001` through `KINGDOMS-003` domain-specific security reviews.

## Historical phase threat models

Phase threat models remain here because they capture the cross-domain risks introduced by the original Phase 0–6 program sequence:

- [Phase 1 threat model](phase-1-threat-model.md) — identity, tenancy, membership, authorization, audit, and outbox foundations.
- [Phase 2 threat model](phase-2-threat-model.md) — public/authored Content, media, and visibility boundaries.
- [Phase 3 threat model](phase-3-threat-model.md) — Events, recurrence, registrations, reminders, and Rally workflows.
- [Phase 4 threat model](phase-4-threat-model.md) — Recruitment intake, reviewer workflows, decisions, conversion, and retention.
- [Phase 5 threat model](phase-5-threat-model.md) — Contribution records, calculations, corrections, exports, and reporting.
- [Phase 6 threat model](phase-6-threat-model.md) — Platform administration, tenant lifecycle, API/webhook access, retention, and scale.

These are historical program evidence. Current domain-specific feature work should use the owning domain's current security-review area rather than extending an old phase threat model into a substitute living contract.

## Domain-specific security evidence

Canonical pattern:

```text
docs/domains/<domain>/security/
  README.md
  <capability>-security-review.md
```

Domain-specific security evidence should cover the assets/trust boundaries, tenant/authorization/privacy/integrity/integration threats, controls, verification, residual risks, and external evidence requirements for that domain.

Current example:

- [Kingdoms security evidence](../domains/kingdoms/security/README.md) — foundation, roster, snapshots, CSV, transfer planning/completion, game-Alliance tracking/observations, diplomacy/contacts, descriptive intelligence, and whole-increment reviews.

Top-level `docs/security/` should not become a flat inventory of security reviews owned by individual code domains.

## Security documentation rules

- Every protected operation requires the authorization model appropriate to its scope.
- Tenant isolation is a security property, not merely a query convention; tenant-boundary changes require isolation tests.
- Privileged operations document identity assurance, MFA/password confirmation where applicable, audit behavior, data minimization, and recovery implications.
- External integrations document authentication/signing, rate limits, replay/idempotency, retry safety, privacy, and egress/SSRF controls.
- Internal outbox publication does not by itself approve a public webhook/API contract.
- Infrastructure-dependent controls remain Pending until real infrastructure evidence exists; application validation cannot prove firewall, ingress, DNS, secret-management, alerting, or recovery configuration by itself.
- Never commit secrets, recovery codes, private keys, credentials, sensitive production payloads, or exploit material that would materially increase operational risk.

## Updating security evidence

For a material change:

1. Update the shared security baseline only when a cross-program rule changes.
2. Update/create the owning domain's security review when the risk belongs primarily to that domain.
3. Keep phase threat models historical unless the change truly concerns the historical program record.
4. Update related domain contracts, ADRs, operations, tests, and acceptance evidence in the same change where applicable.
5. Keep the production security/approval boundary explicit for controls that repository CI cannot prove.
