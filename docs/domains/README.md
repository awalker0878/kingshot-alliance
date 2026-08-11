# Domain documentation

[← Documentation home](../README.md)

This directory owns current business/domain contracts for runtime code under `app/Domain/<CanonicalDomain>`.

The normative structure, naming, format, and CI rules are defined by the [repository documentation standard](../product/documentation-standard.md). Domain-contract depth is governed by the [domain contract standard](../product/domain-contract-standard.md), living security/privacy depth by the [security documentation standard](../product/security-documentation-standard.md), living operations/recovery depth by the [operations documentation standard](../product/operations-documentation-standard.md), interface/event/integration depth by the [interface documentation standard](../product/interface-documentation-standard.md), and testing/evidence traceability by the [testing and evidence standard](../product/testing-evidence-standard.md).

## Canonical rule

Every canonical code domain has exactly one matching living domain contract plus security, operations, interface, and testing/evidence profiles:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
docs/domains/<canonical-domain-kebab>/security/README.md
docs/domains/<canonical-domain-kebab>/operations/README.md
docs/domains/<canonical-domain-kebab>/interfaces/README.md
docs/domains/<canonical-domain-kebab>/testing/README.md
```

The code-local README is concise developer navigation. The matching `/docs` directory is the canonical living domain contract. Capability documents live inside the owning domain directory. Every domain also has one living security profile, operations profile, interface profile, and testing/evidence profile. Focused reviews/runbooks/interface contracts are created only when the relevant focused standard requires them or an already accepted capability contract is explicitly reused. P5 does not create one document per test file.

`README.md` is the **only** Markdown file permitted directly under `docs/domains/`.

The code/documentation ownership relationship is enforced bidirectionally by architecture tests. Code domains and documentation-domain directories must match after canonical normalization; every documentation domain must contain its canonical root plus required profile directories; and flat root-level domain Markdown files are rejected.

## Canonical domain roots

| Code domain | Living domain contract | Security | Operations | Interfaces | Testing/evidence | Primary ownership |
| --- | --- | --- | --- | --- | --- | --- |
| `Alliances` | [alliances/](alliances/README.md) | [security](alliances/security/README.md) | [operations](alliances/operations/README.md) | [interfaces](alliances/interfaces/README.md) | [testing](alliances/testing/README.md) | Alliance aggregate/settings and active-Alliance tenant context. |
| `Audit` | [audit/](audit/README.md) | [security](audit/security/README.md) | [operations](audit/operations/README.md) | [interfaces](audit/interfaces/README.md) | [testing](audit/testing/README.md) | Attributable security/business audit-event recording. |
| `Authorization` | [authorization/](authorization/README.md) | [security](authorization/security/README.md) | [operations](authorization/operations/README.md) | [interfaces](authorization/interfaces/README.md) | [testing](authorization/testing/README.md) | Alliance roles, permissions, role assignment, permission evaluation. |
| `Content` | [content/](content/README.md) | [security](content/security/README.md) | [operations](content/operations/README.md) | [interfaces](content/interfaces/README.md) | [testing](content/testing/README.md) | Authored content, publication/visibility, revisions, media. |
| `Contributions` | [contributions/](contributions/README.md) | [security](contributions/security/README.md) | [operations](contributions/operations/README.md) | [interfaces](contributions/interfaces/README.md) | [testing](contributions/testing/README.md) | Contribution records/calculations/corrections/reporting/exports. |
| `Events` | [events/](events/README.md) | [security](events/security/README.md) | [operations](events/operations/README.md) | [interfaces](events/interfaces/README.md) | [testing](events/testing/README.md) | Event schedules/occurrences/registration/waitlist/attendance/calendar/export. |
| `Identity` | [identity/](identity/README.md) | [security](identity/security/README.md) | [operations](identity/operations/README.md) | [interfaces](identity/interfaces/README.md) | [testing](identity/testing/README.md) | Global User identity, authentication, verification, password/session, MFA. |
| `Integrations` | [integrations/](integrations/README.md) | [security](integrations/security/README.md) | [operations](integrations/operations/README.md) | [interfaces](integrations/interfaces/README.md) | [testing](integrations/testing/README.md) | Read-only API credentials/contracts and signed webhook delivery. |
| `Kingdoms` | [kingdoms/](kingdoms/README.md) | [security](kingdoms/security/README.md) | [operations](kingdoms/operations/README.md) | [interfaces](kingdoms/interfaces/README.md) | [testing](kingdoms/testing/README.md) | Kingdom/player/game-Alliance references, roster/history/intelligence, transfer, diplomacy. |
| `Memberships` | [memberships/](memberships/README.md) | [security](memberships/security/README.md) | [operations](memberships/operations/README.md) | [interfaces](memberships/interfaces/README.md) | [testing](memberships/testing/README.md) | Alliance membership and invitation lifecycle. |
| `Notifications` | [notifications/](notifications/README.md) | [security](notifications/security/README.md) | [operations](notifications/operations/README.md) | [interfaces](notifications/interfaces/README.md) | [testing](notifications/testing/README.md) | Durable Event-reminder and scheduled-report delivery coordination. |
| `Platform` | [platform/](platform/README.md) | [security](platform/security/README.md) | [operations](platform/operations/README.md) | [interfaces](platform/interfaces/README.md) | [testing](platform/testing/README.md) | Cross-tenant administration, lifecycle, entitlements, retention, outbox infrastructure. |
| `Rallies` | [rallies/](rallies/README.md) | [security](rallies/security/README.md) | [operations](rallies/operations/README.md) | [interfaces](rallies/interfaces/README.md) | [testing](rallies/testing/README.md) | Rally guidance, formations, groups, assignments, Rally participation. |
| `Recruitment` | [recruitment/](recruitment/README.md) | [security](recruitment/security/README.md) | [operations](recruitment/operations/README.md) | [interfaces](recruitment/interfaces/README.md) | [testing](recruitment/testing/README.md) | Application intake, candidate pipeline, decisions, onboarding, retention. |

## Capability documents

A capability document is created only when a domain root would otherwise become difficult to navigate or the capability has its own meaningful lifecycle, authorization/privacy, persistence/query, integration, or operational contract.

Capability files use:

```text
docs/domains/<domain>/<capability>.md
```

The folder already identifies the owner, so capability filenames do not repeat the domain name.

### Kingdoms capabilities

`Kingdoms` currently has the accepted deep-dive capability set:

- [Roster](kingdoms/roster.md)
- [Player snapshots](kingdoms/snapshots.md)
- [Roster intelligence](kingdoms/intelligence.md)
- [Controlled CSV migration](kingdoms/csv-migration.md)
- [Transfer planning](kingdoms/transfer-planning.md)
- [Alliance intelligence and diplomacy](kingdoms/alliance-intelligence.md)

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

## Operations profiles and runbooks

Every domain operations profile follows:

```text
docs/domains/<domain>/operations/README.md
```

Focused living operations runbooks follow:

```text
docs/domains/<domain>/operations/<capability>.md
```

The [DCP-P3 operations coverage matrix](../product/operations-coverage-matrix.md) is the frozen inventory. P3 requires 14/14 profiles and the six new focused runbooks for Content, Integrations, Notifications, Platform (two), and Recruitment; Kingdoms retains its three accepted domain operations guides.

Top-level [operations documentation](../operations/README.md) remains authoritative for shared runtime topology/configuration, scheduler/queues/outbox mechanics, observability/health, deployment, backup/restore, rollback, incident response and production launch controls. Domain guides add only domain-specific state/diagnosis/recovery semantics.

## Interface profiles and focused contracts

Every domain interface profile follows:

```text
docs/domains/<domain>/interfaces/README.md
```

New focused P4 interface contracts follow:

```text
docs/domains/<domain>/interfaces/<capability>.md
```

The [DCP-P4 interface coverage matrix](../product/interface-coverage-matrix.md) is the frozen inventory. It covers browser/API surfaces, supported internal actions/queries/services, outbox/event consumers, commands/jobs/scheduled work, file/import/export/media boundaries, external dependencies, versioning/compatibility, and explicit non-capabilities.

P4 adds exactly two new focused contracts because existing accepted capability documents already cover the other independently deep boundaries:

- [Contributions report exports](contributions/interfaces/report-exports.md)
- [Events calendar exports](events/interfaces/calendar-exports.md)

Accepted capability contracts reused by P4 remain in their original canonical locations and are explicitly linked from the owning interface profile. Reuse is preferred to cosmetic duplication when the existing contract is complete.

## Testing and evidence profiles

Every domain testing/evidence profile follows:

```text
docs/domains/<domain>/testing/README.md
```

The [DCP-P5 testing/evidence coverage matrix](../product/testing-evidence-coverage-matrix.md) freezes current evidence classes across the six PHPUnit suites, backend/frontend quality commands, protected workflows, migration/staging/recovery evidence, historical Phase 0–6 acceptance/accessibility records, and Kingdoms K1–K3 evidence.

Testing profiles map **critical claims to evidence classes**. They do not duplicate test source, turn every test file into documentation, or rewrite historical acceptance counts. Historical phase/increment/DCP exit reports remain immutable evidence tied to the SHA/check identities recorded by their acceptance record; living testing profiles describe how the current repository proves its claims today.

The normative identity, retention, supersession, accessibility/performance, migration/recovery and historical-hardening rules are in the [testing and evidence standard](../product/testing-evidence-standard.md).

## Boundary rules

- A domain root describes behavior owned by its matching code domain.
- Cross-domain collaboration uses intentional public actions, queries, services, value objects, enums, or events rather than persistence reach-through.
- Combined user workflows do not justify combining independent code-domain ownership into one canonical file.
- Program-wide product/security/operations/interface/testing governance belongs in `../product/`, `../security/`, and `../operations/`; domain-specific living profiles/evidence stay under the owning domain.
- Capability documents, focused security reviews, focused operations runbooks, and focused interface contracts remain inside the owning domain directory.
- HTTP controller/route placement does not transfer semantic ownership; for example, current Event controllers adapt Rallies-owned actions while Rallies remains the state/contract owner.
- Internal outbox publication does not automatically create an external webhook contract; producer meaning, Platform publication, internal consumers, and Integrations external eligibility remain separate.
- A living security/operations/interface document is not by itself executable proof of a repository-testable invariant; P5 testing profiles identify the applicable validation class.
- A branch name, PR number, or statement that “CI passed” is not sufficient immutable acceptance identity without the exact validated revision/check identities required by the testing/evidence standard.
- Do not create one document per class, controller, route, table, action, query, enum, value object, scheduler command, queue job, or test file.
- Code/tests remain authoritative for exact runtime behavior; documentation drift is a defect to fix.

## Architecture evidence

Cross-domain/repository architecture audits are product/program evidence rather than living domain contracts:

- [Repository structure audit](../product/repository-structure-audit.md)
- [Domain boundary audit](../product/domain-boundary-audit.md)

## Standard domain format

Every `docs/domains/<domain>/README.md` follows the living-domain section order in the [domain contract standard](../product/domain-contract-standard.md). Capability files use its standard capability-contract format. Security profiles/focused reviews use the [security documentation standard](../product/security-documentation-standard.md). Operations profiles/focused runbooks use the [operations documentation standard](../product/operations-documentation-standard.md). Interface profiles/new focused contracts use the [interface documentation standard](../product/interface-documentation-standard.md). Testing/evidence profiles use the [testing and evidence standard](../product/testing-evidence-standard.md).

## Updating domain documentation

When behavior changes materially, update the matching domain root and affected capability/security/operations/interface/testing documents together with code-local README, tests, shared operations, ADRs, capability/status records, accessibility evidence, and acceptance evidence as applicable.

The structure is intentionally deterministic: a contributor can derive the canonical contract, security profile, operations profile, interface profile, and testing/evidence profile paths directly from `app/Domain/<Domain>` without repository search or tribal knowledge.
