# Domain boundary audit

[← Product and program documentation](README.md)

**Document type:** Current domain architecture evidence  
**Status:** Current  
**P6 baseline audited from:** P5 final transition `983b662bac8873ba2eb71ccec8a6c9e5d1331923`  
**Audit refresh:** `DCP-P6`

## Purpose

This audit records current semantic ownership boundaries for the modular monolith. It summarizes high-risk ownership evidence; it does not replace the [implementation plan](implementation-plan.md), accepted [ADRs](../adr/README.md), living domain contracts, [cross-domain dependency map](cross-domain-dependency-map.md), or architecture tests.

## Canonical owners

Canonical domains are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, `Recruitment`.

Primary ownership is:

- **Identity** — global User identity/authentication/verified email/password/session/MFA/recovery assurance.
- **Alliances** — platform Alliance aggregate, Alliance settings, active tenant context, Alliance→Kingdom association.
- **Memberships** — membership and invitation lifecycle.
- **Authorization** — Alliance roles, permission keys, effective rank, permission evaluation.
- **Audit** — attributable security/business evidence.
- **Content** — public/member authored content, revisions, publication/visibility, categories/media/presentation content.
- **Events** — Event schedules/templates/occurrences, recurrence, registration/waitlist/cancellation, Event attendance, calendar/export behavior.
- **Rallies** — Rally guidance/formations/groups/assignments/Rally participation.
- **Notifications** — durable Event-reminder and scheduled Contribution-report due-time coordination.
- **Recruitment** — application intake, candidate/reviewer/decision/onboarding/retention workflow.
- **Contributions** — contribution facts/calculations/corrections/reporting/data quality/exports/report schedules+runs.
- **Integrations** — API credentials/read contracts and webhook subscription/signing/delivery/retry/external-event eligibility.
- **Platform** — cross-tenant administration/lifecycle/plans/entitlements/settings/legal holds/retention/usage/export orchestration and transactional-outbox infrastructure.
- **Kingdoms** — neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/intelligence/import/transfer/diplomacy/contact workflows.

Use [the glossary](glossary.md) where `Alliance`/`KingdomAlliance`, identity/membership/authorization, internal/external event, or accepted/approved terminology could be ambiguous.

## Cross-domain contract rule

A domain collaborates through an intentional supported owner contract: action/service/query/value object/enum/domain event/outbox contract/explicit reference contract/documented adapter.

Cross-domain imports are not inherently invalid. The architecture prohibits accidental ownership transfer: persistence reach-through, duplicate writable truth, hidden tenant inference, or treating another domain's internal row as locally owned state.

The authoritative system-level direction is [Cross-domain dependency map](cross-domain-dependency-map.md). Bidirectional workflow collaboration is allowed where state ownership remains explicit.

## Enforced high-risk boundaries

`tests/Architecture/DomainBoundaryTest.php` protects known rules including:

1. Alliances does not absorb Content persistence merely because Content is Alliance-scoped.
2. Recruitment does not treat Memberships `Invitation` persistence as Recruitment-owned state.
3. Feature domains do not duplicate generic outbox writers; Platform owns transactional-outbox infrastructure.

Other Architecture suites protect physical ownership, tenant isolation, Kingdoms boundaries, documentation ownership, interfaces, operations, security, and testing/evidence traceability.

## Identity, tenancy, authorization

- Identity is global; Alliance access is not.
- Active tenant context is Alliances-owned and requires Memberships-owned active membership for normal tenant access.
- Authorization evaluates stable permission/effective-rank contracts; it does not infer authority from role display names.
- Platform administrator grants are cross-tenant and are never Alliance roles.
- Identity assurance (verified email, MFA, recent password confirmation) strengthens privileged workflows but never replaces authorization.

## Content and Recruitment

Recruitment settings are authoritative for recruitment/application availability. Content may present that state publicly but does not own a duplicate writable recruitment switch.

## Events, Rallies, Notifications, Contributions

- Events owns Event scheduling/occurrences/registration/waitlists/cancellation/Event attendance.
- Rallies owns Rally guidance/formations/groups/assignments/Rally participation.
- Notifications owns durable Event-reminder state even though Events owns the source reminder/occurrence context.
- Contributions may reconcile from Events attendance but does not edit attendance truth.
- Notifications coordinates due Contribution report requests; Contributions owns report schedule/version/run/report semantics.

These collaborations can be bidirectional at the application-contract level without creating duplicate persistence ownership.

## Integrations, Platform, producer domains

- Integrations owns machine credentials, API scope enforcement, webhook subscriptions/signing/delivery/retries.
- Platform owns lifecycle/entitlement controls and outbox/queue infrastructure without absorbing Integrations persistence.
- Producer domains own event-specific business payload semantics.
- Internal outbox publication never automatically creates a public webhook contract.
- Kingdoms external API/webhook exposure remains explicitly unapproved/unimplemented.

## Kingdoms, Alliances, Memberships

- Alliances owns the platform tenant and `kingdom_id` association.
- Kingdoms owns neutral `Kingdom`, `Player`, `KingdomAlliance` identity plus tenant-owned Kingdoms observations/workflows.
- Memberships owns application membership even when Kingdoms stores optional same-Alliance membership references.
- stable game IDs are automatic neutral identity keys only inside their documented game scope; names/tags/handles do not auto-merge identity.
- sharing a neutral Kingdoms reference never grants cross-Alliance access.
- coordinator assignments and diplomacy contacts never grant application authorization.

## Audit and transactional outbox

Audit owns attributable evidence. Platform owns transactional-outbox infrastructure. Feature domains own the business transition that determines when either record is required.

Audit/outbox payloads must remain bounded and must not absorb credentials, bearer secrets, recovery material, or unnecessary private narrative simply because the source business row contains it.

## Platform orchestration boundary

Platform may coordinate lifecycle, retention, legal-hold, usage, tenant-complete export, and ownership-transfer workflows across domains through supported projections/actions. This orchestration does not make Platform the persistence owner of every participating feature domain.

Payment processing and support impersonation remain intentionally unimplemented.

## Current external boundary

Integrations is the external machine-access owner. Current accepted machine surfaces are Alliance-bound read-only API credentials for approved Alliance/Events/Contributions reads and signed outbound webhooks for explicitly eligible events.

An internal `kingdoms.*` event, neutral Kingdoms reference, or platform outbox message does not imply a public API scope or externally eligible webhook.

## Shared documentation ownership

P6 confirms current top-level documentation is correctly scoped:

- product = cross-program governance/current-state/audits/historical phase-wide acceptance/production decisions;
- security = shared baseline/historical threat models/production security boundary;
- operations = shared runtime/runbooks/historical phase-wide operating evidence;
- ADR = durable architecture decisions/current architecture index.

Domain implementation/security/operations/interfaces/testing/evidence stay with the owning domain.

No additional domain-specific relocation is required by P6.

## Preserved historical context

The 2026-08-08 boundary audit correctly established durable rules around Event attendance, Recruitment availability, Notifications reminder ownership, Platform-admin separation, Integrations ownership, and explicit tenant context. At that time Kingdoms runtime was not yet implemented.

That pre-Kingdoms statement is historical only. K1–K3 subsequently established accepted Kingdoms runtime ownership. P6 preserves the historical fact without allowing it to remain current architecture guidance.

## Validation and maintenance

Refresh this audit when:

- persistence/business ownership moves;
- a new supported cross-domain contract is introduced;
- the canonical domain set changes;
- an accepted ADR changes system direction;
- a product increment changes an explicit non-capability; or
- architecture tests materially change the protected boundary model.

If runtime/contracts and this audit disagree, treat the mismatch as a defect. Update the owning architecture/code/tests/living docs rather than documenting drift as a compatibility state.
