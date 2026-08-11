# Domain documentation

[← Documentation home](../README.md)

This directory owns current business/domain contracts for runtime code under `app/Domain/<CanonicalDomain>`.

The normative structure, naming, format, and CI rules are defined by the [repository documentation standard](../product/documentation-standard.md). Domain-contract depth is governed by the [domain contract standard](../product/domain-contract-standard.md), and living security/privacy depth by the [security documentation standard](../product/security-documentation-standard.md).

## Canonical rule

Every canonical code domain has exactly one matching documentation root:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
docs/domains/<canonical-domain-kebab>/security/README.md
```

The code-local README is concise developer navigation. The matching `/docs` directory is the canonical living domain contract. Capability documents live inside the owning domain directory. Every domain also has one living security profile under its own `security/` directory; focused security reviews are created only when the security-documentation standard requires them.

`README.md` is the **only** Markdown file permitted directly under `docs/domains/`.

The relationship is enforced bidirectionally by `tests/Architecture/RepositoryStructureTest.php`: code domains and documentation-domain directories must match after canonical normalization, every documentation-domain directory must contain `README.md`, every code domain must have the required security profile, and flat root-level domain Markdown files are rejected.

## Canonical domain roots

| Code domain | Canonical living contract | Living security profile | Primary ownership |
| --- | --- | --- | --- |
| `Alliances` | [alliances/](alliances/README.md) | [security](alliances/security/README.md) | Alliance aggregate/settings and active-Alliance tenant context. |
| `Audit` | [audit/](audit/README.md) | [security](audit/security/README.md) | Attributable security/business audit-event recording. |
| `Authorization` | [authorization/](authorization/README.md) | [security](authorization/security/README.md) | Alliance roles, permissions, role assignment, permission evaluation. |
| `Content` | [content/](content/README.md) | [security](content/security/README.md) | Authored content, publication/visibility, revisions, media. |
| `Contributions` | [contributions/](contributions/README.md) | [security](contributions/security/README.md) | Contribution records/calculations/corrections/reporting/exports. |
| `Events` | [events/](events/README.md) | [security](events/security/README.md) | Event schedules/occurrences/registration/waitlist/attendance/calendar/export. |
| `Identity` | [identity/](identity/README.md) | [security](identity/security/README.md) | Global User identity, authentication, verification, password/session, MFA. |
| `Integrations` | [integrations/](integrations/README.md) | [security](integrations/security/README.md) | Read-only API credentials/contracts and signed webhook delivery. |
| `Kingdoms` | [kingdoms/](kingdoms/README.md) | [security](kingdoms/security/README.md) | Kingdom/player/game-Alliance references, roster/history/intelligence, transfer, diplomacy. |
| `Memberships` | [memberships/](memberships/README.md) | [security](memberships/security/README.md) | Alliance membership and invitation lifecycle. |
| `Notifications` | [notifications/](notifications/README.md) | [security](notifications/security/README.md) | Durable Event-reminder and scheduled-report delivery coordination. |
| `Platform` | [platform/](platform/README.md) | [security](platform/security/README.md) | Cross-tenant administration, lifecycle, entitlements, retention, outbox infrastructure. |
| `Rallies` | [rallies/](rallies/README.md) | [security](rallies/security/README.md) | Rally guidance, formations, groups, assignments, Rally participation. |
| `Recruitment` | [recruitment/](recruitment/README.md) | [security](recruitment/security/README.md) | Application intake, candidate pipeline, decisions, onboarding, retention. |

## Capability documents

A capability document is created only when a domain root would otherwise become difficult to navigate or the capability has its own meaningful lifecycle, authorization/privacy, persistence/query, integration, or operational contract.

Capability files use:

```text
docs/domains/<domain>/<capability>.md
```

The folder already identifies the owner, so capability filenames do not repeat the domain name.

### Kingdoms capabilities

`Kingdoms` currently has the accepted deep-dive capability set:

- [Roster](kingdoms/roster.md) — neutral player identity and Alliance-owned roster state.
- [Player snapshots](kingdoms/snapshots.md) — append-oriented player observation history and freshness.
- [Roster intelligence](kingdoms/intelligence.md) — exact aggregates, data quality, linkage/movement, bounded trends.
- [Controlled CSV migration](kingdoms/csv-migration.md) — strict dry-run/confirm roster migration/export contract.
- [Transfer planning](kingdoms/transfer-planning.md) — transfer cycles, participants, groups, readiness/blockers, explicit completion/handoff.
- [Alliance intelligence and diplomacy](kingdoms/alliance-intelligence.md) — tracked game-side Alliances, observations, explicit diplomacy, private contacts, descriptive intelligence.

## Security profiles and reviews

Every domain security profile follows:

```text
docs/domains/<domain>/security/README.md
```

Focused living reviews follow:

```text
docs/domains/<domain>/security/<capability>-security-review.md
```

The [DCP-P2 security coverage matrix](../product/security-coverage-matrix.md) is the frozen inventory for which focused reviews are required. Historical phase/increment security evidence remains historical and is not rewritten merely to match the living-review format.

Top-level [security documentation](../security/README.md) remains cross-domain/shared. Domain-specific living security behavior belongs under the code-owning domain.

## Boundary rules

- A domain root describes behavior owned by its matching code domain.
- Cross-domain collaboration uses intentional public actions, queries, services, value objects, enums, or events rather than persistence reach-through.
- Combined user workflows do not justify combining independent code-domain ownership into one canonical file.
- Program-wide product/security/operations policy belongs in `../product/`, `../security/`, and `../operations/`; domain-specific product/security/operations evidence stays under the owning domain.
- Capability documents remain inside the owning domain directory.
- Do not create one document per class, controller, route, table, action, query, enum, or value object.
- Code/tests remain authoritative for exact runtime behavior; documentation drift is a defect to fix.

## Architecture evidence

Cross-domain/repository architecture audits are product/program evidence rather than living domain contracts:

- [Repository structure audit](../product/repository-structure-audit.md)
- [Domain boundary audit](../product/domain-boundary-audit.md)

## Standard domain format

Every `docs/domains/<domain>/README.md` follows the living-domain section order in the [domain contract standard](../product/domain-contract-standard.md):

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

Capability files use the standard 12-section capability-contract format. Security profiles and focused reviews use the formats in the [security documentation standard](../product/security-documentation-standard.md).

## Updating domain documentation

When behavior changes materially, update the matching domain root and any affected capability/security documents together with code-local README, tests, operations docs, ADRs, capability/status records, accessibility evidence, and acceptance evidence as applicable.

The structure is intentionally deterministic: a contributor can derive the canonical documentation and security paths directly from `app/Domain/<Domain>` without repository search or tribal knowledge.
