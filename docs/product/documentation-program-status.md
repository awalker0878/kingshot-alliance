# Documentation Completion Program status

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program status ledger  
**Status:** Current  
**Program ID:** `DCP`

## Current control state

**Recorded next phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Transition state:** P3 complete transition recorded; exact final evidence/status head protected validation required before P4 work starts  
**Control decision on next `continue`:** If this final head is protected-green, begin P4; otherwise finish the exposed P3/evidence defect

DCP-P1 and DCP-P2 remain fully closed with their accepted evidence. DCP-P3 has completed its frozen content inventory and corrected candidate gate: 14/14 living domain operations profiles, 6/6 new focused living operations runbooks, three accepted Kingdoms operations guides retained/indexed, normalized shared/domain navigation, complete recovery/rollback references, and deterministic P3 architecture enforcement.

Corrected P3 candidate evidence head `a67f93706eff4285a229df1f6ce057f2be3b5adc` passed protected Dependency Review `31508211709`, CodeQL `31508211738`, and CI `31508211931`, including frontend, PostgreSQL migrations, Pint 484 files, PHPStan 345/345 with 0 errors, 375 tests / 7,628 assertions, P3 architecture and local-link validation, immutable image build, staging, backup/restore and image scan.

This ledger records P3 as Complete and P4 as the next/current phase **conditionally on protected success of the exact branch head containing this final P3 exit/status evidence**. No P4 implementation may begin before that external exact-head gate succeeds. After it succeeds, the transition is authoritative without another branch mutation; the exact final SHA/check IDs may be recorded in PR metadata to avoid a self-referential commit cycle.

## Phase ledger

| Phase | Name | Status | Exit decision |
| --- | --- | --- | --- |
| `DCP-P0` | Governance and continuation controls | Complete | Program plan, completeness standard, status ledger and navigation established. |
| `DCP-P1` | Domain contract and code-ownership completeness | Complete | 14 domain maps, 14 canonical contracts, 19 material capability contracts, P1 CI enforcement, and protected validation complete. See [P1 exit report](domain-contract-completeness-exit-report.md). |
| `DCP-P2` | Security, privacy, and data-protection completeness | Complete | 14 domain security profiles, 9 new focused reviews, normalized Kingdoms security evidence, P2 CI enforcement, and protected candidate/final-head validation complete. See [P2 exit report](security-completeness-exit-report.md). |
| `DCP-P3` | Operations, reliability, and recovery completeness | Complete* | 14 operations profiles, 6 new focused runbooks, 3 retained Kingdoms guides, P3 CI enforcement and corrected candidate protected validation complete. `*` Transition authority requires the exact final evidence/status head to be protected-green. See [P3 exit report](operations-completeness-exit-report.md). |
| `DCP-P4` | Interfaces, events, and integrations completeness | Current* | Recorded next phase. `*` Work remains blocked until the exact final P3 evidence/status head is protected-green. |
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

## DCP-P3 accepted candidate evidence

- [Operations documentation standard](operations-documentation-standard.md)
- [Operations coverage matrix](operations-coverage-matrix.md)
- [DCP-P3 exit report](operations-completeness-exit-report.md)
- 14 living `docs/domains/<domain>/operations/README.md` profiles
- 6 new focused living operations runbooks
- normalized Kingdoms living operations profile with three accepted K1–K3 guides retained
- shared operations/domain navigation to all profiles
- P3 structural/metadata/heading/frozen-inventory checks in `tests/Architecture/OperationsDocumentationTest.php`

Corrected P3 content candidate: `b6f4aa9ca929ff75fef48344423eee7891210d26`.

The initial P3 evidence head `9f03f918daa16d63cfbac538b57755289677d35d` passed Dependency Review `31507721516` and CodeQL `31507721523`. CI `31507721345` failed only Pint's `no_unused_imports` check on four unused iterator imports in the newly added operations-documentation architecture test. Frontend quality/build and PostgreSQL migrations were green; container/staging/recovery was skipped after the PHP gate failure. The unused imports were removed without changing P3 documentation or validation semantics.

Corrected P3 candidate evidence head: `a67f93706eff4285a229df1f6ce057f2be3b5adc`.

Protected corrected-candidate runs:

- Dependency Review `31508211709` — success.
- CodeQL `31508211738` — success.
- CI `31508211931` — success:
  - frontend quality/build — success;
  - PostgreSQL migrations — success;
  - Pint — 484 files;
  - PHPStan/Larastan — 345/345, 0 errors;
  - ParaTest/PHPUnit — 375 tests / 7,628 assertions;
  - P3 architecture/profile/runbook assertions — success;
  - repository-wide local Markdown-link validation — success;
  - immutable image build — success;
  - ephemeral staging — success;
  - backup/restore — success; and
  - image scan — success.

### Frozen focused P3 runbooks

- Content — `scheduled-publishing-and-media.md`
- Integrations — `webhook-delivery.md`
- Notifications — `scheduled-delivery.md`
- Platform — `transactional-outbox.md`
- Platform — `lifecycle-retention.md`
- Recruitment — `retention-and-anonymization.md`

Alliances, Audit, Authorization, Contributions, Events, Identity, Memberships and Rallies are profile-only domains. Kingdoms retains its accepted roster-intelligence, transfer-planning and Alliance-intelligence operating guides.

## DCP-P4 entry rule

When the exact final P3 evidence/status branch head is protected-green, DCP-P4 becomes authoritative. P4 then inventories and standardizes current interfaces, events, and integrations, including where applicable:

- public/internal actions, queries and services;
- routes/controllers and browser/API surfaces;
- domain/application events;
- transactional-outbox event types and consumers;
- scheduler commands and queue jobs;
- import/export contracts;
- read API credentials/scopes/endpoints;
- webhook subscription/event/payload/signature/delivery contracts;
- external dependency boundaries; and
- explicit non-capabilities such as the current lack of a public Kingdoms API/webhook contract.

P4 must freeze its own code-backed inventory and focused documentation standard before adding completeness CI. P5 remains blocked until P4 fully passes its candidate and final-head gates.

## `continue` procedure

On `continue`:

1. Treat the phase transition recorded above as authoritative only if its exact final evidence/status head has passed protected validation.
2. If that exact head is not green, remain in P3 and correct only the exposed defect.
3. If green, begin/evaluate P4 against the [Documentation completeness standard](documentation-completeness-standard.md) and [program plan](documentation-program-plan.md).
4. If required P4 documentation remains incomplete, keep P4 active and finish it.
5. Only when P4 is fully complete may the ledger advance to P5.
6. Never advance around incomplete required documentation.

The detailed definition of `Complete` is normative in [Documentation completeness standard](documentation-completeness-standard.md).
