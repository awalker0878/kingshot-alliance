# Kingshot Alliance documentation

This directory is the canonical documentation entry point for the Kingshot Alliance repository.

## Current program state

The implementation plan ends at **Phase 6**. Phases 0–6 and the repository-controlled production-hardening stage are complete and accepted. A real production cutover is **not yet approved**; infrastructure and operational evidence is still required before `product/production-launch-approval.md` can be changed to Approved.

Start with:

- [Current capability matrix](product/current-capability-matrix.md) — what the current product can and cannot do, with links to the owning living contracts.
- [Architecture decisions and current architecture view](adr/README.md) — current system map plus the accepted decisions behind it.
- [Implementation plan](product/implementation-plan.md) — authoritative product/program scope, phase boundaries, architecture principles, and delivery model.
- [Production hardening exit report](product/production-hardening-exit-report.md) — accepted repository-controlled hardening evidence.
- [Production launch approval](product/production-launch-approval.md) — authoritative go/no-go record for a real production cutover.
- [Operations](operations/README.md) — current runtime configuration, background processing, observability, deployment, recovery, and release guidance.
- [Security baseline](security/security-baseline.md) — cross-cutting security requirements.

## Reader paths

### Developer

Use this path when changing application behavior, domain contracts, architecture, tests, or runtime implementation:

1. [Architecture decisions and current architecture view](adr/README.md) — understand the modular-monolith topology, domain boundaries, persistence/asynchronous flow, and relevant ADRs.
2. [Current capability matrix](product/current-capability-matrix.md) — confirm whether the capability already exists and find its owning living contract.
3. [Domain documentation](domains/README.md) — read the owning domain guide and boundary rules.
4. [Security documentation](security/README.md) — identify security, tenancy, authorization, and integration requirements affected by the change.
5. [Operations documentation](operations/README.md) — update configuration, scheduler/queue, observability, deployment, or recovery guidance when runtime behavior changes.
6. Code and tests — treat implementation/tests as authoritative for exact runtime behavior and update documentation when they diverge.

For a material architecture change, update or add an ADR rather than silently redefining architecture in a feature guide.

### Alliance/operator

Use this path when administering an alliance or understanding the product capabilities available to alliance leaders/coordinators:

1. [Current capability matrix](product/current-capability-matrix.md) — identify the available capability and explicit current boundaries.
2. [Identity, tenancy, and membership](domains/identity-tenancy-and-membership.md) — membership lifecycle, invitations, roles, permissions, and RBAC.
3. Follow the relevant living feature guide:
   - [Content management](domains/content-management.md)
   - [Events and rallies](domains/events-and-rallies.md)
   - [Recruitment](domains/recruitment.md)
   - [Contributions and reporting](domains/contributions-and-reporting.md)
   - [Notifications](domains/notifications.md)
   - [Integrations](domains/integrations.md)
4. [Platform scale and administration](domains/platform-scale-and-administration.md) — understand which controls belong to cross-tenant platform operators rather than alliance roles.

Phase exit reports are implementation-history evidence, not the alliance operating manual or user-facing changelog.

### Security reviewer

Use this path for threat, control, tenant-isolation, privileged-access, or production-security review:

1. [Security baseline](security/security-baseline.md) — current cross-cutting security requirements.
2. [Architecture decisions and current architecture view](adr/README.md) — trust boundaries, tenancy, data stores, asynchronous processing, and integration boundaries.
3. [Identity, tenancy, and membership](domains/identity-tenancy-and-membership.md) — authentication, MFA/password confirmation, active-alliance context, RBAC, and tenant invariants.
4. [Integrations](domains/integrations.md) — API credentials, scopes, webhook signing/retry, and SSRF/egress boundary.
5. [Observability](operations/observability.md) and [Runtime configuration reference](operations/configuration-reference.md) — operational signals, secrets/configuration controls, proxy/TLS assumptions, and current telemetry limitations.
6. [Production launch security review](security/production-launch-security-review.md) and [production launch approval](product/production-launch-approval.md) — repository evidence versus external controls still required for a real launch.

Use phase threat models when reviewing how a risk was introduced historically; use the security baseline and current living contracts for present behavior.

### Production operator

Use this path to configure, deploy, operate, recover, or assess launch readiness:

1. [Operations documentation](operations/README.md) — current operating model and runbook index.
2. [Runtime configuration reference](operations/configuration-reference.md) — establish a valid hosted runtime and worker configuration.
3. [Deployment runbook](operations/runbooks/deployment.md) and [release checklist](operations/release-checklist.md) — deploy the immutable image and capture release evidence.
4. [Observability](operations/observability.md) — verify liveness/readiness, logs/correlation, queues, outbox/webhooks, and launch-health signals.
5. [Background processing](operations/background-processing.md) — verify scheduler ownership, Horizon queues, retries, idempotency, and safe catch-up/recovery.
6. [Rollback](operations/runbooks/rollback.md), [backup and restore](operations/runbooks/backup-restore.md), and [incident response](operations/runbooks/incident-response.md) — use when a stop/recovery condition is reached.
7. [Production launch approval](product/production-launch-approval.md) and [production launch runbook](operations/production-launch-runbook.md) — confirm external evidence and accountable go/no-go approval before treating the service as production-approved.

A green CI/staging/recovery demonstration does not by itself approve production infrastructure or operations.

## Documentation map

| Area | Purpose |
|---|---|
| [`adr/`](adr/README.md) | Current architecture view plus material architecture decisions and their consequences. |
| [`domains/`](domains/README.md) | Living domain behavior, ownership, boundaries, and implementation guidance. |
| [`operations/`](operations/README.md) | Living runtime operations, deployment, recovery, release, and historical operating evidence. |
| [`product/`](product/README.md) | Current capability/status navigation, program plan, phase evidence, accessibility reviews, acceptance, and launch status. |
| [`security/`](security/README.md) | Current security baseline/launch review plus historical phase threat models. |

The five directories above are the only canonical top-level documentation groups. Do not add parallel structures such as `docs/wiki/`, `docs/architecture/`, or `docs/runbooks/`; place new material in the owning canonical group.

## Source-of-truth rules

When documents overlap, use this precedence:

1. `product/implementation-plan.md` defines approved program scope and canonical repository structure.
2. Accepted ADRs define architectural decisions within that scope.
3. Current product-state records such as `current-capability-matrix.md`, `production-hardening-exit-report.md`, and `production-launch-approval.md` define present capability/status navigation and acceptance/go-no-go state.
4. Domain, operations, and security documents define implementation and operating details.
5. Phase exit reports, phase threat models, migration notes, and accessibility reviews are historical evidence for the phase they describe. Statements such as “next phase” inside those records should be read in their historical context rather than as the current roadmap.

The capability matrix is a navigation/status summary; it does not override the implementation plan or code/tests. Code and tests remain authoritative for implemented runtime behavior. If implementation and documentation conflict, treat the discrepancy as a defect and update the appropriate source rather than adding a compatibility note that preserves known drift.

## Documentation conventions

- Use lowercase kebab-case for descriptive Markdown filenames. Keep `README.md` for directory indexes and preserve numbered ADR filenames such as `0008-domain-first-source-layout.md`.
- Use repository-relative Markdown links.
- Prefer one clear source of truth over duplicated explanations.
- State whether a document is normative guidance, current status, a runbook, or historical phase evidence.
- Record real evidence identifiers; never mark infrastructure or operational controls complete because CI merely passed.
- Keep secrets, credentials, private endpoint details, and sensitive incident evidence outside the repository.
- Update related runbooks, threat models, ADRs, and acceptance records in the same change when behavior materially changes.
- Preserve the canonical domain-first names used by `app/Domain` and the implementation plan.

## Historical delivery and future launch documentation

For historical implementation delivery, start at the [product index](product/README.md) and follow the relevant phase exit report to its operations, threat, accessibility, or migration evidence.

Release notes and end-user onboarding are intentionally separate from phase acceptance history. Create those as production-launch/user-facing artifacts when a real release requires them rather than retroactively converting Phase 0–6 exit reports into a changelog.
