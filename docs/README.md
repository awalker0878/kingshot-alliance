# Kingshot Alliance documentation

This directory is the canonical documentation entry point for the Kingshot Alliance repository.

## Current program state

The implementation plan ends at **Phase 6**. Phases 0–6 and the repository-controlled production-hardening stage are complete and accepted. A real production cutover is **not yet approved**; infrastructure and operational evidence is still required before `product/production-launch-approval.md` can be changed to Approved.

Start with:

- [Implementation plan](product/implementation-plan.md) — authoritative product/program scope, phase boundaries, architecture principles, and delivery model.
- [Production hardening exit report](product/production-hardening-exit-report.md) — accepted repository-controlled hardening evidence.
- [Production launch approval](product/production-launch-approval.md) — authoritative go/no-go record for a real production cutover.
- [Release checklist](operations/release-checklist.md) — release execution and evidence checklist.
- [Security baseline](security/security-baseline.md) — cross-cutting security requirements.
- [Architecture decisions](adr/README.md) — accepted architectural decisions and repository structure.

## Documentation map

| Area | Purpose |
|---|---|
| [`adr/`](adr/README.md) | Material architecture decisions and their consequences. |
| [`domains/`](domains/README.md) | Domain behavior, ownership, boundaries, and implementation guidance. |
| [`operations/`](operations/README.md) | Deployment, recovery, migration, release, and production runbooks. |
| [`product/`](product/README.md) | Program plan, phase evidence, accessibility reviews, acceptance, and launch status. |
| [`security/`](security/README.md) | Security baseline, phase threat models, and production security review. |

The five directories above are the only canonical top-level documentation groups. Do not add parallel structures such as `docs/wiki/`, `docs/architecture/`, or `docs/runbooks/`; place new material in the owning canonical group.

## Source-of-truth rules

When documents overlap, use this precedence:

1. `product/implementation-plan.md` defines approved program scope and canonical repository structure.
2. Accepted ADRs define architectural decisions within that scope.
3. Current product-state records such as `production-hardening-exit-report.md` and `production-launch-approval.md` define present acceptance/go-no-go status.
4. Domain, operations, and security documents define implementation and operating details.
5. Phase exit reports, phase threat models, migration notes, and accessibility reviews are historical evidence for the phase they describe. Statements such as “next phase” inside those records should be read in their historical context rather than as the current roadmap.

Code and tests remain authoritative for implemented runtime behavior. If implementation and documentation conflict, treat the discrepancy as a defect and update the appropriate source rather than adding a compatibility note that preserves known drift.

## Documentation conventions

- Use lowercase kebab-case for descriptive Markdown filenames. Keep `README.md` for directory indexes and preserve numbered ADR filenames such as `0008-domain-first-source-layout.md`.
- Use repository-relative Markdown links.
- Prefer one clear source of truth over duplicated explanations.
- State whether a document is normative guidance, current status, a runbook, or historical phase evidence.
- Record real evidence identifiers; never mark infrastructure or operational controls complete because CI merely passed.
- Keep secrets, credentials, private endpoint details, and sensitive incident evidence outside the repository.
- Update related runbooks, threat models, ADRs, and acceptance records in the same change when behavior materially changes.
- Preserve the canonical domain-first names used by `app/Domain` and the implementation plan.

## Common paths

**Developing a feature:** implementation plan → owning domain guide → relevant ADRs/security model → tests.

**Operating the service:** operations index → production launch runbook/release checklist → applicable runbook → security baseline.

**Reviewing historical delivery:** product index → phase exit report → phase operations/threat/accessibility/migration evidence.

**Approving production:** production launch approval → production launch runbook → release checklist → captured external evidence.
