# Domain boundary audit

[← Product and program documentation](README.md)

**Document type:** Domain architecture evidence  
**Status:** Current migration audit — final validated SHA to be recorded after protected checks  
**Prior runtime audited at:** `b908407b68f2567ebcd5b9e43ebf1d842844b20a`  
**Prior audit date:** 2026-08-08

## Purpose

This audit records semantic ownership boundaries for the current modular-monolith runtime. It preserves the evidence intent of the earlier `docs/domains/domain-boundary-audit.md` while updating the stale pre-Kingdoms assumption that `Kingdoms` was documentation-only.

The [implementation plan](implementation-plan.md), accepted ADRs, [documentation standard](documentation-standard.md), current living domain contracts, and architecture tests remain normative. This file summarizes high-risk ownership evidence rather than defining a competing architecture.

## Current domain ownership

Canonical domain roots are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

High-level ownership is:

- **Identity** — global User identity, authentication, verified email, password/session security, MFA, recovery codes.
- **Alliances** — Alliance aggregate, Alliance creation/settings, active-Alliance context, Alliance→Kingdom association.
- **Memberships** — membership and invitation lifecycle.
- **Authorization** — Alliance roles, permission keys, role assignment/removal, permission evaluation.
- **Audit** — attributable security/business audit-event recording.
- **Content** — public/member content, revisions, media, publication/visibility, Alliance presentation content.
- **Events** — Event schedules/templates/occurrences, registration/waitlist/cancellation, Event attendance, Event export/calendar behavior.
- **Rallies** — Rally guidance, saved/recommended formations, groups, assignments, Rally participation.
- **Notifications** — durable Event-reminder delivery state and scheduled Contribution-report due-time coordination.
- **Recruitment** — application intake, candidate pipeline, reviewer workflow, decisions, onboarding, metrics, retention.
- **Contributions** — contribution categories/records/calculations/corrections/reporting/data quality/exports/report schedules+runs.
- **Integrations** — API credentials, read-only API contract, webhook subscriptions, signing, delivery, retries, external-event eligibility.
- **Platform** — cross-tenant administration, Alliance lifecycle, plans/entitlements, settings/feature flags, legal holds, retention orchestration, usage, transactional outbox.
- **Kingdoms** — neutral Kingdom/player/game-Alliance references plus Alliance-owned roster/history/intelligence/CSV migration, transfer planning, game-Alliance observations/diplomacy/contacts/descriptive intelligence.

## Cross-domain contract rule

A domain may collaborate with another only through an intentional supported contract. Appropriate surfaces include public actions, queries, services, value objects, enums, and domain events where ownership requires them.

The architecture does **not** require zero cross-domain imports. It requires dependencies to express explicit business ownership rather than accidental persistence reach-through. Material coupling should be exposed through an intentional contract or recorded in an ADR.

## Enforced high-risk boundaries

`tests/Architecture/DomainBoundaryTest.php` protects known high-risk rules including:

1. `Alliances` does not own Content persistence relationships merely because content belongs to an Alliance.
2. Recruitment does not import/use Memberships' `Invitation` persistence as its own; accepted-candidate conversion consumes a supported Memberships contract.
3. Feature domains do not keep duplicate generic outbox-writer services; transactional outbox infrastructure is Platform-owned.

Additional Architecture tests protect domain-first physical layout and Kingdoms ownership/acceptance boundaries.

## Important current boundaries

### Identity / tenant / authorization

- Identity is global; Alliance access is not.
- Active tenant context is Alliances-owned and requires active Memberships-owned membership.
- Authorization consumes active membership and stable permission vocabulary; it does not infer authority from role names alone.
- Platform administrator grants are cross-tenant and are not Alliance roles.

### Content / Recruitment

Recruitment settings are authoritative for recruitment availability. Content may display that state but does not maintain a duplicate writable recruitment-status field.

### Events / Rallies / Notifications / Contributions

- Event scheduling/occurrences/registration/Event attendance are Events-owned.
- Rally guidance/formations/groups/assignments/Rally participation are Rallies-owned.
- Event reminder delivery state is Notifications-owned even when Events configures reminder timing.
- Contributions may derive records from Events attendance but does not edit attendance truth.
- Notifications coordinates due Contribution report requests; Contributions owns report schedules, versions, runs, and report semantics.

### Integrations / Platform / producer domains

- Integrations owns credentials/subscriptions/delivery/signing/retries.
- Platform may enforce availability/entitlements and owns outbox infrastructure without absorbing integration persistence.
- Producer domains own business event payload semantics.
- Internal outbox event existence never automatically creates an external webhook contract.

### Kingdoms / Alliances / Memberships

- Alliances owns the Alliance aggregate and `kingdom_id` setting.
- Kingdoms owns neutral Kingdom/player/game-Alliance reference identity and Alliance-owned game observations/workflows.
- Memberships owns application membership even when a Kingdoms roster entry optionally links to it.
- Stable game IDs are identity keys only within their Kingdom; names/tags/handles do not auto-merge.
- Shared neutral Kingdom/player/game-Alliance references never grant cross-tenant access.
- Coordinator assignments and diplomacy contacts never grant authorization.

### Audit / Platform outbox

Audit owns attributable evidence; Platform owns transactional outbox infrastructure. Feature domains own the transitions that decide when each record is required. Audit/outbox payloads must not absorb private notes/secrets simply because the business row contains them.

## Preserved 2026-08-08 audit context

The earlier audit correctly established these durable ownership rules:

- Event attendance is Events-owned;
- Recruitment availability is Recruitment-owned rather than duplicated in Content;
- Event reminder delivery is Notifications-owned;
- Platform administration is not an Alliance role;
- API credentials/webhooks are Integrations-owned while Platform provides lifecycle/entitlement controls; and
- Alliance tenant context must be explicit/revalidated rather than inferred from hidden global state.

It also recorded the architectural principle that stable ownership/contracts are more useful than freezing raw class/import counts.

The earlier statement that Kingdoms was a reserved documentation-only root is now historical: `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` subsequently added accepted runtime ownership.

## Documentation ownership map

The canonical living documentation mirrors code ownership:

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

Cross-domain workflow narratives may link multiple domain roots, but they do not justify a combined canonical living file that obscures ownership.

## Validation and maintenance

Review this audit when:

- a domain begins owning a new business lifecycle;
- persistence ownership moves between domains;
- a new cross-domain contract is introduced;
- an Architecture test is added/removed;
- the canonical domain set changes; or
- a product increment changes a previously explicit non-capability into accepted runtime behavior.

If this document and runtime disagree, treat the discrepancy as a defect. Update normative architecture/code/tests/living domain docs rather than documenting drift as a compatibility state.
