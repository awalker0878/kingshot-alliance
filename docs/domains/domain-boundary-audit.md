# Domain boundary audit

[← Domain documentation](README.md)

**Status:** Current  
**Runtime audited at:** `b908407b68f2567ebcd5b9e43ebf1d842844b20a`  
**Audit date:** 2026-08-08

## Purpose

This audit records the current semantic ownership boundaries after completion of Phases 0–6 and the domain-first architecture alignment. It replaces the earlier Phase 0–4 inventory whose file counts and zero-runtime assumptions became stale after Contributions and Integrations were implemented.

The [implementation plan](../product/implementation-plan.md) and accepted ADRs remain normative. This document summarizes current boundary evidence and the high-risk invariants protected by architecture tests.

## Current domain ownership

The canonical domain roots are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

All except `Kingdoms` own runtime PHP in the completed Phase 0–6 implementation. `Kingdoms` remains intentionally documentation-only until additional game/kingdom reference capability is explicitly approved.

High-level ownership is:

- **Identity** — global user identity, authentication, account security, MFA, and profile lifecycle.
- **Alliances** — alliance aggregate, active-alliance context, alliance creation, and alliance-level composition surfaces.
- **Memberships** — membership and invitation lifecycle.
- **Authorization** — alliance roles, permissions, authorization services, and role assignment.
- **Audit** — attributable audit-event recording.
- **Content** — public/member content, revisions, media, and alliance presentation content.
- **Events** — event schedules, occurrences, registrations, attendance, recurrence, and event coordination.
- **Rallies** — rally guidance, formations, groups, assignments, and rally participation behavior.
- **Notifications** — durable reminder/report delivery state and notification coordination.
- **Recruitment** — application intake, candidate pipeline, reviewer workflow, decisions, onboarding, and candidate retention.
- **Contributions** — contribution records, calculations, corrections, reporting, data quality, and exports.
- **Integrations** — API credentials, read-only integration endpoints, webhook subscriptions, delivery, signing, and retry behavior.
- **Platform** — cross-tenant administration, platform lifecycle controls, plans/entitlements, legal holds, retention orchestration, usage, and transactional-outbox infrastructure.
- **Kingdoms** — reserved documentation-only ownership root.

## Cross-domain contract rule

A domain may collaborate with another domain only through an intentional supported contract. Appropriate cross-domain surfaces include public actions, queries, services, value objects, enums, and domain events where the ownership model requires them.

The architecture does **not** require zero cross-domain imports. It requires that dependencies reflect explicit business ownership rather than accidental reach-through into another domain's persistence internals. When a dependency becomes material architectural coupling, either expose an intentional contract or record the decision in an ADR.

## Enforced high-risk boundaries

`tests/Architecture/DomainBoundaryTest.php` currently protects these known high-risk rules:

1. The `Alliances` aggregate does not own Content-domain relationships in `Alliance.php`; Content remains responsible for its own persistence and presentation records.
2. Recruitment does not import Memberships' `Invitation` persistence model when converting accepted candidates; it uses the membership/invitation contract instead of taking ownership of invitation persistence.
3. Feature domains do not keep duplicate outbox-writer services. Shared transactional-outbox recording is owned by `Platform/Services/OutboxRecorder.php`.

`tests/Architecture/DomainStructureTest.php` separately protects canonical domain ownership for all runtime PHP and ensures the reserved `Kingdoms` domain remains runtime-free while Phase 6 `Integrations` runtime is present.

## Important current boundaries

The completed implementation also follows these documented ownership rules:

- Event attendance remains Events-owned; Contributions may derive records from it but does not independently edit attendance truth.
- Recruitment settings are authoritative for recruitment availability; Content does not maintain a duplicate writable recruitment-status field.
- Event reminder delivery state is Notifications-owned even when Events configures reminder rules.
- Platform administration is a cross-tenant grant and is not modeled as an alliance role.
- Integration credentials and webhook subscriptions are Integrations-owned; Platform may enforce entitlements and lifecycle controls without absorbing integration persistence.
- Alliance tenant context is explicit and must be revalidated rather than inferred from hidden global state.

These rules are described in the applicable current domain guides and the consolidated [security baseline](../security/security-baseline.md).

## Historical audit boundary

Earlier architecture inventories were useful during the Phase 1–4 domain-first refactor, but raw class counts and import counts are not stable architecture contracts. They became misleading once Phases 5 and 6 added Contributions and Integrations runtime.

This audit therefore records stable ownership and test-enforced invariants instead of freezing implementation counts. Historical phase acceptance reports remain unchanged except for navigation/reference maintenance.

## Validation and maintenance

Review this audit when:

- a domain begins owning a new business lifecycle;
- persistence ownership moves between domains;
- a new cross-domain contract is introduced;
- an architecture test is added or removed;
- the canonical domain set changes through an approved plan/ADR change.

If this document and runtime behavior disagree, treat the discrepancy as a defect. Update the architecture source and tests rather than documenting known drift as an accepted compatibility condition.
