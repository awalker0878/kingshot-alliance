# Domain documentation

[← Documentation home](../README.md)

This directory owns current business/domain contracts for runtime code under `app/Domain/<CanonicalDomain>`.

The normative structure, naming, format, migration, and CI rules are defined by the [repository documentation standard](../product/documentation-standard.md).

## Canonical rule

Every canonical code domain has exactly one matching documentation root:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
```

The code-local README is concise developer navigation. The matching `/docs` directory is the canonical domain documentation root. Large domains may add capability files inside their own directory, for example `docs/domains/kingdoms/roster.md`.

The relationship is enforced bidirectionally in `tests/Architecture/RepositoryStructureTest.php`: a code domain without a docs domain is invalid, and a docs domain without a code domain is invalid.

## Canonical domain roots

| Code domain | Documentation root | Current documentation state |
| --- | --- | --- |
| `Alliances` | [alliances/](alliances/README.md) | Root established; detailed contract migration pending |
| `Audit` | [audit/](audit/README.md) | Root established; detailed contract migration pending |
| `Authorization` | [authorization/](authorization/README.md) | Root established; detailed contract migration pending |
| `Content` | [content/](content/README.md) | Root established; migrate `content-management.md` |
| `Contributions` | [contributions/](contributions/README.md) | Root established; migrate `contributions-and-reporting.md` |
| `Events` | [events/](events/README.md) | Root established; split from `events-and-rallies.md` |
| `Identity` | [identity/](identity/README.md) | Root established; split from combined identity/tenancy guide |
| `Integrations` | [integrations/](integrations/README.md) | Root established; migrate existing `integrations.md` |
| `Kingdoms` | [kingdoms/](kingdoms/README.md) | Root established; migrate root and capability guides |
| `Memberships` | [memberships/](memberships/README.md) | Root established; split from combined identity/tenancy guide |
| `Notifications` | [notifications/](notifications/README.md) | Root established; migrate existing `notifications.md` |
| `Platform` | [platform/](platform/README.md) | Root established; migrate `platform-scale-and-administration.md` |
| `Rallies` | [rallies/](rallies/README.md) | Root established; split from `events-and-rallies.md` |
| `Recruitment` | [recruitment/](recruitment/README.md) | Root established; migrate existing `recruitment.md` |

## Capability-document rule

Capability documents live beneath the owning domain:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/<capability>.md
```

The domain folder already establishes ownership, so capability filenames do not repeat the domain prefix.

Example target for Kingdoms:

```text
docs/domains/kingdoms/
  README.md
  roster.md
  snapshots.md
  intelligence.md
  csv-migration.md
  transfer-planning.md
  alliance-intelligence.md
```

Do not create one document per class, controller, action, query, table, or enum. Use a capability document when the capability has a meaningful independent lifecycle, authorization/privacy boundary, persistence/query contract, integration contract, or enough complexity that the root README becomes difficult to navigate.

## Current migration-source guides

The following flat guides remain valid migration sources until `DOCS-P1`/`DOCS-P2` moves their authoritative content into the domain folders:

- [Identity, tenancy, and membership](identity-tenancy-and-membership.md)
- [Content management](content-management.md)
- [Events and rallies](events-and-rallies.md)
- [Contributions and reporting](contributions-and-reporting.md)
- [Integrations](integrations.md)
- [Kingdoms](kingdoms.md)
- [Kingdoms roster](kingdoms-roster.md)
- [Kingdoms player snapshots](kingdoms-snapshots.md)
- [Kingdoms roster intelligence](kingdoms-intelligence.md)
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md)
- [Kingdoms transfer planning](kingdoms-transfer-planning.md)
- [Kingdoms alliance intelligence](kingdoms-alliance-intelligence.md)
- [Notifications](notifications.md)
- [Platform scale and administration](platform-scale-and-administration.md)
- [Recruitment](recruitment.md)

Do not remove a migration source until its content has been moved, all repository links have been updated, code-local READMEs point to the new root, and documentation architecture tests pass.

## Architecture evidence

- [Repository/domain structure audit](repository-structure-audit.md) — physical-layout evidence.
- [Domain boundary audit](domain-boundary-audit.md) — semantic ownership and intentional cross-domain contract evidence.

These are evidence records, not canonical domain roots. They remain root-level historical/current evidence during the documentation migration.

## Standard domain format

After `DOCS-P1`, every `docs/domains/<domain>/README.md` uses the exact living-domain section order defined in the [documentation standard](../product/documentation-standard.md):

1. Purpose and ownership.
2. Scope.
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
17. Capability documents.
18. Related documentation.

## Boundary rules

- A domain root describes behavior owned by its matching code domain.
- Cross-domain collaboration references intentional public actions, queries, services, value objects, enums, or events rather than persistence reach-through.
- Combined user workflows do not justify combining independent code-domain ownership into one canonical root.
- Product scope/status belongs in `../product/`; security evidence in `../security/`; operational procedures in `../operations/`; architecture rationale in `../adr/`.
- Capability documents stay inside the owning domain directory.
- Code/tests remain authoritative for exact runtime behavior; documentation drift is a defect to fix, not a compatibility state to preserve.

## Updating domain documentation

When domain behavior changes materially, update the matching domain root together with affected capability docs, code-local README, security, operations, ADR, capability/status, accessibility, and acceptance evidence as required.

The target state is deterministic: a contributor can derive the documentation path directly from `app/Domain/<Domain>` without repository search or tribal knowledge.
