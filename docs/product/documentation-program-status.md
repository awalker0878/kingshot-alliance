# Documentation Completion Program status

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program status ledger  
**Status:** Current  
**Program ID:** `DCP`

## Current control state

**Current phase:** `DCP-P3` — Operations, reliability, and recovery completeness  
**Gate status:** Candidate — 100% required content implemented; protected validation pending  
**Control decision on next `continue`:** Finish current phase

DCP-P2 is fully closed. Its final accepted evidence/status head `35121bf732f75c72351a7c232548f3e78fb1c8ff` passed protected Dependency Review `31505325682`, CodeQL `31505325673`, and CI `31505325711` including frontend, PHP/documentation architecture tests, immutable image, staging, backup/restore and image scan.

DCP-P3 has now implemented its frozen operations/reliability/recovery inventory: 14/14 living domain operations profiles, 6/6 new focused living operations runbooks, the existing three Kingdoms K1–K3 operations guides retained/indexed, shared/domain navigation normalized, recovery/rollback references completed, and deterministic P3 architecture enforcement added.

P3 remains active until protected validation passes on the exact candidate/evidence head and the final exit/status evidence head also passes. P4 is blocked until that hard gate closes.

## Phase ledger

| Phase | Name | Status | Exit decision |
| --- | --- | --- | --- |
| `DCP-P0` | Governance and continuation controls | Complete | Program plan, completeness standard, status ledger and navigation established. |
| `DCP-P1` | Domain contract and code-ownership completeness | Complete | 14 domain maps, 14 canonical contracts, 19 material capability contracts, P1 CI enforcement, and protected validation complete. See [P1 exit report](domain-contract-completeness-exit-report.md). |
| `DCP-P2` | Security, privacy, and data-protection completeness | Complete | 14 domain security profiles, 9 new focused reviews, normalized Kingdoms security evidence, P2 CI enforcement, and protected candidate/final-head validation complete. See [P2 exit report](security-completeness-exit-report.md). |
| `DCP-P3` | Operations, reliability, and recovery completeness | Candidate | **Current phase.** 100% frozen content inventory implemented; protected candidate/final exit recording remain. See [P3 exit report](operations-completeness-exit-report.md). |
| `DCP-P4` | Interfaces, events, and integrations completeness | Not started | Blocked by `DCP-P3`. |
| `DCP-P5` | Testing, evidence, and traceability completeness | Not started | Blocked by `DCP-P4`. |
| `DCP-P6` | Architecture and program-governance consolidation | Not started | Blocked by `DCP-P5`. |
| `DCP-P7` | Maintenance automation and final acceptance | Not started | Blocked by `DCP-P6`. |

## DCP-P1 accepted evidence

Validated P1 candidate head: `be4a87734b44fa09643b6e8e5066283b5ed4fece`.

Protected candidate runs:

- Dependency Review `31500031422` — success.
- CodeQL `31500031623` — success.
- CI `31500031488` — success, including 483 Pint files, PHPStan 345/345 with 0 errors, 365 tests / 6,136 assertions, immutable image build, staging, backup/restore, and image scan.

Final accepted P1 evidence/status head: `60357543256478aa8ef8c26f67e27631df8c5ba4`, also protected-green.

## DCP-P2 accepted evidence

Corrected P2 content candidate: `645c943e59439840d3563452d97612eb17d63b10`.

Corrected candidate evidence head: `eea41be6bf45820a7f3ab06f57cc24703e7d2b8e`.

Protected candidate runs:

- Dependency Review `31504587302` — success.
- CodeQL `31504587346` — success.
- CI `31504587198` — success, including Pint 483 files, PHPStan 345/345 with 0 errors, 369 tests / 6,908 assertions, immutable image build, staging, backup/restore, and image scan.

Final accepted P2 evidence/status head: `35121bf732f75c72351a7c232548f3e78fb1c8ff`.

Protected final-head runs:

- Dependency Review `31505325682` — success.
- CodeQL `31505325673` — success.
- CI `31505325711` — success, including frontend, PHP/documentation architecture tests, immutable image, staging, backup/restore, and scan.

## DCP-P3 candidate evidence

- [Operations documentation standard](operations-documentation-standard.md)
- [Frozen operations coverage matrix](operations-coverage-matrix.md)
- [DCP-P3 exit report](operations-completeness-exit-report.md)
- 14 living `docs/domains/<domain>/operations/README.md` profiles
- 6 required new focused living operations runbooks
- normalized Kingdoms living operations profile with three accepted K1–K3 guides retained
- shared operations/domain navigation to all profiles
- P3 structural/metadata/heading/frozen-inventory checks in `tests/Architecture/OperationsDocumentationTest.php`

P3 content candidate recorded by the exit report: `55dd2d29cb1c45dd3c01e9e42f6b57a8a9118c3d`.

### Frozen focused P3 runbooks

- Content — `scheduled-publishing-and-media.md`
- Integrations — `webhook-delivery.md`
- Notifications — `scheduled-delivery.md`
- Platform — `transactional-outbox.md`
- Platform — `lifecycle-retention.md`
- Recruitment — `retention-and-anonymization.md`

Alliances, Audit, Authorization, Contributions, Events, Identity, Memberships and Rallies are complete profile-only domains. Kingdoms retains its accepted roster-intelligence, transfer-planning and Alliance-intelligence operating guides.

## P3 operational coverage summary

The living operations set now covers, where applicable:

- persistent runtime state and ownership;
- hosted/runtime configuration dependencies;
- scheduler, Horizon/queue and transactional-outbox participation;
- implemented health/observability/diagnostic signals;
- failure-mode diagnosis;
- safe retry, replay, reconciliation and catch-up;
- database/private-media/secret/external-recipient recovery-set boundaries;
- application rollback versus database migration/restore semantics;
- capacity/query/performance assumptions and regression-versus-capacity evidence boundaries;
- external dependency degradation behavior;
- safe operator actions, prohibited shortcuts and escalation stop conditions; and
- evidence identifiers/operators should retain.

Shared `docs/operations/` remains authoritative for runtime topology/configuration, background processing, health/observability, deployment, backup/restore, rollback, incident response and production launch controls. Domain-specific operating semantics stay under their code-owning domain.

## `continue` procedure

On `continue`:

1. Treat the phase marked **Current phase** above as authoritative.
2. Evaluate it against the [Documentation completeness standard](documentation-completeness-standard.md) and its phase exit criteria in the [program plan](documentation-program-plan.md).
3. If required documentation remains incomplete, keep this phase active and finish that work.
4. If the phase is a complete candidate, run/finalize the required validation and exit evidence.
5. Only when the phase is fully complete may this ledger mark it `Complete`, select the next phase as **Current phase**, and begin the next phase.
6. Never advance around incomplete required documentation.

## Status update rules

When this ledger changes phase:

- preserve completed phase rows;
- make exactly one unfinished phase the **Current phase**;
- summarize the evidence supporting the completed gate;
- update the next phase status to `Not started` or `In progress` as appropriate;
- keep later phases blocked by their predecessor; and
- do not mark a phase complete based solely on partial inventory or green CI.

The detailed definition of `Complete` is normative in [Documentation completeness standard](documentation-completeness-standard.md).
