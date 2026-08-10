# Domain documentation

[← Documentation home](../README.md)

This directory owns the current business/domain contracts for runtime code under `app/Domain/<CanonicalDomain>`.

The normative documentation structure, naming, format, and migration rules are defined by the [repository documentation standard](../product/documentation-standard.md).

## Canonical rule

Every canonical code domain must have exactly one predictable living anchor:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>.md
```

The code-local README is a concise developer navigation surface. The `/docs` file is the full living domain contract. Large domains may add detail documents named `<domain>-<capability>.md`, but those documents never replace the canonical `<domain>.md` anchor.

## Canonical domain coverage

The repository has 14 canonical runtime domains.

| Code domain | Canonical living document | Current migration state |
| --- | --- | --- |
| `Alliances` | `alliances.md` | Missing; content currently spans broader identity/tenancy/admin guides |
| `Audit` | `audit.md` | Missing |
| `Authorization` | `authorization.md` | Missing; currently covered inside `identity-tenancy-and-membership.md` |
| `Content` | `content.md` | Missing canonical name; migrate `content-management.md` |
| `Contributions` | `contributions.md` | Missing canonical name; migrate `contributions-and-reporting.md` |
| `Events` | `events.md` | Missing; split from `events-and-rallies.md` |
| `Identity` | `identity.md` | Missing; split from `identity-tenancy-and-membership.md` |
| `Integrations` | [integrations.md](integrations.md) | Canonical anchor present |
| `Kingdoms` | [kingdoms.md](kingdoms.md) | Canonical anchor present |
| `Memberships` | `memberships.md` | Missing; split from `identity-tenancy-and-membership.md` |
| `Notifications` | [notifications.md](notifications.md) | Canonical anchor present |
| `Platform` | `platform.md` | Missing canonical name; migrate `platform-scale-and-administration.md` |
| `Rallies` | `rallies.md` | Missing; split from `events-and-rallies.md` |
| `Recruitment` | [recruitment.md](recruitment.md) | Canonical anchor present |

The missing paths above describe the **target inventory** under `DOCS-P1`; they must not be linked as if they already exist until they are created and populated.

## Current migration-source guides

These existing guides remain usable until their canonical replacements are complete:

- [Identity, tenancy, and membership](identity-tenancy-and-membership.md) — migration source for `identity.md`, `alliances.md`, `memberships.md`, and `authorization.md`.
- [Content management](content-management.md) — migration source for `content.md`.
- [Events and rallies](events-and-rallies.md) — migration source for `events.md` and `rallies.md`.
- [Contributions and reporting](contributions-and-reporting.md) — migration source for `contributions.md`.
- [Platform scale and administration](platform-scale-and-administration.md) — migration source for `platform.md`.

After the canonical documents contain the full current contracts and repository links have moved, these competing broad guides should be removed rather than preserved indefinitely as redirect/stub documentation.

## Canonical and capability guides already in use

### Integrations

- [Integrations](integrations.md) — API credential/authentication contract, read-only API endpoints, webhook signing/delivery contract, and integration boundaries.

### Notifications

- [Notifications](notifications.md) — event-reminder delivery state, scheduled-report coordination, idempotency, scheduler flow, and recovery.

### Recruitment

- [Recruitment](recruitment.md) — application modes, candidate pipeline, review, decisions, conversion, metrics, and retention.

### Kingdoms

The `Kingdoms` anchor is intentionally decomposed into domain-prefixed capability contracts:

- [Kingdoms](kingdoms.md) — canonical Kingdoms ownership map and cross-capability boundary.
- [Kingdoms roster](kingdoms-roster.md) — game-player identity, alliance-owned roster, membership linkage, authorization, filtering, data minimization, and tenant isolation.
- [Kingdoms player snapshots](kingdoms-snapshots.md) — append-only observations, idempotency, latest projection, freshness, history visibility, and provenance.
- [Kingdoms roster intelligence](kingdoms-intelligence.md) — exact aggregates, data-quality indicators, bounded trends, movement/linkage summaries, and manager comparison boundary.
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md) — schema, dry-run classification, identity resolution, atomic/idempotent confirmation, provenance, and export safety.
- [Kingdoms transfer planning](kingdoms-transfer-planning.md) — transfer cycles, participants, destinations, groups/coordinators, readiness/blockers, completion/handoff, and tenant/privacy boundaries.
- [Kingdoms alliance intelligence](kingdoms-alliance-intelligence.md) — neutral game-alliance tracking, factual observations/history, explicit diplomacy, private contacts, and descriptive intelligence.

## Architecture evidence

- [Repository/domain structure audit](repository-structure-audit.md) — physical-layout evidence.
- [Domain boundary audit](domain-boundary-audit.md) — semantic ownership and intentional cross-domain contract evidence.

Audits are evidence records. They do not replace canonical living domain documents and must be refreshed when their audited assumptions become stale.

## Domain document standard

Every canonical `docs/domains/<domain>.md` follows the required section order in the [documentation standard](../product/documentation-standard.md):

1. Purpose and ownership.
2. Scope and non-scope.
3. Domain model.
4. Core invariants.
5. Lifecycles and workflows.
6. Authorization and tenancy.
7. Cross-domain contracts.
8. Persistence and data ownership.
9. Events, outbox and integrations.
10. HTTP, UI and API surfaces.
11. Background processing.
12. Failure, idempotency and concurrency.
13. Security and privacy.
14. Observability and operations.
15. Testing and architecture enforcement.
16. Explicit non-capabilities.
17. Related documentation.

A section may state `Not applicable` with a reason; important contract areas should not silently disappear simply because a particular domain does not use them.

## Boundary rules

- A canonical guide describes behavior owned by its matching code domain.
- Cross-domain collaboration references intentional public actions, queries, services, value objects, enums, or events rather than persistence reach-through.
- Combined user workflows do not justify combining independent code-domain ownership into one canonical file.
- Product scope/status belongs in `../product/`; threat/security evidence belongs in `../security/`; operational procedures belong in `../operations/`; architecture rationale belongs in `../adr/`.
- Capability documents use `<domain>-<capability>.md` and are linked from the canonical domain anchor.
- Do not create one Markdown document per model, controller, route, table, action, or query.
- Code/tests remain authoritative for exact runtime behavior; documentation drift is a defect to fix, not a compatibility state to preserve.

## Updating domain documentation

When domain behavior changes materially, update the matching canonical domain contract together with affected code-local README, security, operations, ADR, capability/status, accessibility, or acceptance evidence as required.

The target end state is deterministic: from a code path `app/Domain/<Domain>`, a contributor can derive the canonical living document path without searching the repository.
