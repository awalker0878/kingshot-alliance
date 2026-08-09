# Security documentation

[← Documentation home](../README.md)

This directory owns security requirements, threat models, and launch-security evidence. Security documentation supplements—not replaces—policy enforcement in code, tests, infrastructure, and operational controls.

## Start here

- [Security baseline](security-baseline.md) — cross-cutting requirements for authentication, authorization, tenancy, data handling, transport, secrets, dependencies, audit, and operational security.
- [KINGDOMS-001 whole-increment security review](kingdoms-roster-intelligence-security-review.md) — cross-slice acceptance review for global reference identity, tenant-owned roster/history/import/intelligence, identity ambiguity, CSV/export safety, authorization, internal outbox events and public integration boundaries.
- [Kingdoms foundation security review](kingdoms-foundation-security-review.md) — Slice A evidence for global Kingdom references, alliance association, migration, authorization, audit/outbox, and tenant-boundary risks.
- [Kingdoms roster security review](kingdoms-roster-security-review.md) — Slice B evidence for neutral game-player identity, alliance roster tenancy, membership linkage, private manager data, authorization, search/filter isolation, and audit/outbox controls.
- [Kingdoms snapshot security review](kingdoms-snapshot-security-review.md) — Slice C1 evidence for append-only snapshot tenancy, replay/idempotency, actor privacy, capture-time integrity, integer precision, latest projection, and history controls.
- [Kingdoms intelligence security review](kingdoms-intelligence-security-review.md) — Slice C2 evidence for tenant-scoped aggregation, missing/stale-data interpretation, bounded trend windows, exact arithmetic, comparison privacy, and anti-ranking controls.
- [Kingdoms CSV migration security review](kingdoms-csv-security-review.md) — Slice D evidence for untrusted CSV parsing, identity ambiguity, preview/commit drift, batch atomicity/idempotency, formula injection, private export fields, provenance and tenant isolation.
- [Production launch security review](production-launch-security-review.md) — repository-controlled launch-security review and the external controls that remain operational responsibilities.
- [Production launch approval](../product/production-launch-approval.md) — authoritative real-production go/no-go record.

The slice reviews record the risk introduced in each implementation slice. The whole-increment review is the `K1-P6` security evidence surface and must be read when accepting the combined Kingdoms workflow.

## Phase threat models

- [Phase 1 threat model](phase-1-threat-model.md) — identity, tenancy, membership, authorization, audit, and outbox foundations.
- [Phase 2 threat model](phase-2-threat-model.md) — public content, authored content, media, and visibility boundaries.
- [Phase 3 threat model](phase-3-threat-model.md) — events, recurrence, registrations, reminders, and rally workflows.
- [Phase 4 threat model](phase-4-threat-model.md) — recruitment intake, reviewer workflows, decisions, conversion, and retention.
- [Phase 5 threat model](phase-5-threat-model.md) — contribution records, calculations, corrections, exports, and reporting.
- [Phase 6 threat model](phase-6-threat-model.md) — platform administration, tenant lifecycle, API/webhook access, retention, and scale.

Threat models are historical evidence for the phase in which the risk was introduced. Current post-program feature work should use a current security review when the historical phase models are no longer the right evidence surface. The security baseline and current launch-security/approval records continue to govern production readiness.

## Security documentation rules

- Every protected operation requires policy-based authorization appropriate to its scope.
- Tenant isolation is a security property, not merely a query convention; changes that affect tenant boundaries require isolation tests.
- Privileged operations should document identity assurance, MFA/password-confirmation requirements, audit behavior, and recovery implications.
- External integrations must document authentication/signing, rate limits, replay/idempotency behavior, retry safety, and egress/SSRF controls.
- Internal outbox publication does not by itself approve an external webhook contract; externally eligible events must match the documented integration boundary.
- Security controls that depend on infrastructure must remain Pending until real infrastructure evidence exists. Application validation cannot truthfully prove firewall, ingress, DNS, secret-management, alerting, or recovery configuration by itself.
- Never commit secrets, recovery codes, private keys, credentials, sensitive production payloads, or exploit proof that would materially increase operational risk.

## Updating security evidence

For a material change, update the owning threat model or create/update a current security review when the old phase model is no longer the right evidence surface. Also update related ADRs, operations guidance, tests, and product acceptance records in the same PR where appropriate.

A new threat model/security review should identify assets, trust boundaries, attackers/abuse cases, controls, residual risks, verification, and any external evidence that must be owned outside the repository.
