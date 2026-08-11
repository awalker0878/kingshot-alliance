# Security documentation

[← Documentation home](../README.md)

This directory owns **repository-wide security policy and program evidence**: the shared security baseline, phase-wide historical threat history, and production-launch security boundary. Living security/privacy behavior that primarily belongs to one code domain lives with that owner under `docs/domains/<domain>/security/`.

Security documentation supplements—not replaces—authorization in code, tenant-isolation tests, infrastructure controls, operations, and accountable production evidence.

## Start here

- [Security baseline](security-baseline.md) — current cross-cutting requirements for authentication, authorization, tenancy, data handling, transport, secrets, dependencies, audit, integrations, and operational security.
- [Security documentation standard](../product/security-documentation-standard.md) — DCP-P2 normative structure/ownership/completeness requirements for domain security profiles and focused living reviews.
- [Security coverage matrix](../product/security-coverage-matrix.md) — frozen DCP-P2 repository-wide domain/focused-review inventory.
- [Domain documentation index](../domains/README.md) — deterministic navigation to every code domain and its living security profile.
- [Production launch security review](production-launch-security-review.md) — repository-controlled launch-security review plus external controls that remain production responsibilities.
- [Production launch approval](../product/production-launch-approval.md) — authoritative real-production go/no-go record.

## Current living domain security profiles

Every canonical code domain has one current security/privacy profile:

- [Alliances](../domains/alliances/security/README.md)
- [Audit](../domains/audit/security/README.md)
- [Authorization](../domains/authorization/security/README.md)
- [Content](../domains/content/security/README.md)
- [Contributions](../domains/contributions/security/README.md)
- [Events](../domains/events/security/README.md)
- [Identity](../domains/identity/security/README.md)
- [Integrations](../domains/integrations/security/README.md)
- [Kingdoms](../domains/kingdoms/security/README.md)
- [Memberships](../domains/memberships/security/README.md)
- [Notifications](../domains/notifications/security/README.md)
- [Platform](../domains/platform/security/README.md)
- [Rallies](../domains/rallies/security/README.md)
- [Recruitment](../domains/recruitment/security/README.md)

The owning profile is the current map for assets, trust boundaries, tenant/privacy behavior, secrets, abuse cases, destructive operations, evidence, residual risks, and required focused reviews. Use the owning domain contract for complete business/runtime semantics and the shared baseline for cross-domain policy.

## Historical phase threat models

Phase threat models remain here because they capture the cross-domain risks introduced by the original Phase 0–6 program sequence:

- [Phase 1 threat model](phase-1-threat-model.md) — identity, tenancy, membership, authorization, audit, and outbox foundations.
- [Phase 2 threat model](phase-2-threat-model.md) — public/authored Content, media, and visibility boundaries.
- [Phase 3 threat model](phase-3-threat-model.md) — Events, recurrence, registrations, reminders, and Rally workflows.
- [Phase 4 threat model](phase-4-threat-model.md) — Recruitment intake, reviewer workflows, decisions, conversion, and retention.
- [Phase 5 threat model](phase-5-threat-model.md) — Contribution records, calculations, corrections, exports, and reporting.
- [Phase 6 threat model](phase-6-threat-model.md) — Platform administration, tenant lifecycle, API/webhook access, retention, and scale.

These are **historical evidence**, not the current source of truth for domain-owned behavior. Current feature/domain security behavior is documented in the owning domain security profile and living focused reviews. Historical threat models remain useful for when/why controls were introduced and for preserved phase-exit evidence.

## Domain-specific security evidence

Canonical living pattern:

```text
docs/domains/<domain>/security/
  README.md
  <capability>-security-review.md
```

Every `security/README.md` is mandatory. A focused living review exists only when the [security documentation standard](../product/security-documentation-standard.md) identifies an independently high-risk capability, such as tenant-boundary establishment, secret/bearer credential lifecycle, private untrusted file storage, anonymous/external network exposure, destructive/privacy orchestration, or shared replay-sensitive infrastructure.

Kingdoms also retains its accepted K1–K3 security review set beneath its [living security profile](../domains/kingdoms/security/README.md). Those increment reviews remain accepted evidence and are not cosmetically rewritten into the newer P2 focused-review format.

Top-level `docs/security/` must not become a flat inventory of security reviews owned by individual code domains.

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
2. Update the owning domain's living security profile whenever its security/privacy boundary changes.
3. Update/create a focused living review when the capability crosses the review threshold in the security documentation standard.
4. Keep phase/increment threat reviews historical unless the change truly concerns the historical record.
5. Update related domain contracts, ADRs, operations, tests, and acceptance evidence in the same change where applicable.
6. Keep the production security/approval boundary explicit for controls that repository CI cannot prove.
