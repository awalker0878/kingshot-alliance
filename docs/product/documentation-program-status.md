# Documentation Completion Program status

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program status ledger  
**Status:** Current  
**Program ID:** `DCP`

## Current control state

**Current phase:** `DCP-P5` — Testing, evidence, and traceability completeness  
**Gate status:** Selected — DCP-P4 content/candidate complete; this exact P4 final evidence/status head must be protected-green before P5 implementation begins  
**Control decision on next `continue`:** If this exact head is protected-green, begin/finish P5; otherwise remain at the P4 final gate

DCP-P4 has completed its frozen interface/event/integration inventory and its exact protected candidate gate. The final P4 transition recorded by this ledger is effective only when the exact resulting branch head independently passes protected Dependency Review, CodeQL, and complete CI.

P4 content candidate: `3ebd2ec3a25432baa636840911995be1a451f9c2`.  
Validated P4 candidate/evidence head: `66b2ca498ac89e550d3e718b174e07172e7409bd`.

Protected candidate validation passed:

- Dependency Review `31512996437` — success;
- CodeQL `31512996420` — success; and
- CI `31512996421` — success, including frontend, PostgreSQL migrations, 485 Pint files, PHPStan/Larastan 345/345 with 0 errors, 381 tests / 8,290 assertions, P4 architecture/interface/route-inventory assertions, repository Markdown-link validation, immutable image build, ephemeral staging, backup/restore and image scan.

P5 implementation remains blocked until this final transition head is protected-green. Green candidate checks alone are not sufficient to skip the final evidence-head rule.

## Phase ledger

| Phase | Name | Status | Exit decision |
| --- | --- | --- | --- |
| `DCP-P0` | Governance and continuation controls | Complete | Program plan, completeness standard, status ledger and navigation established. |
| `DCP-P1` | Domain contract and code-ownership completeness | Complete | 14 domain maps, 14 canonical contracts, 19 material capability contracts, P1 CI enforcement, and protected validation complete. See [P1 exit report](domain-contract-completeness-exit-report.md). |
| `DCP-P2` | Security, privacy, and data-protection completeness | Complete | 14 domain security profiles, 9 new focused reviews, normalized Kingdoms security evidence, P2 CI enforcement, and protected candidate/final-head validation complete. See [P2 exit report](security-completeness-exit-report.md). |
| `DCP-P3` | Operations, reliability, and recovery completeness | Complete | 14 operations profiles, 6 new focused runbooks, 3 retained Kingdoms guides, P3 CI enforcement, corrected candidate and final-head protected validation complete. See [P3 exit report](operations-completeness-exit-report.md). |
| `DCP-P4` | Interfaces, events, and integrations completeness | Complete | 14 interface profiles, 2 new focused contracts, accepted contract reuse, route/bootstrap/command/outbox/API/webhook/file inventory, P4 CI enforcement and protected candidate validation complete. Final transition subject to this exact-head protected gate. See [P4 exit report](interface-completeness-exit-report.md). |
| `DCP-P5` | Testing, evidence, and traceability completeness | Selected | **Next phase.** Do not implement until the exact P4 final evidence/status head is protected-green. |
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

## DCP-P3 accepted evidence

Corrected P3 content candidate: `b6f4aa9ca929ff75fef48344423eee7891210d26`.

Initial P3 evidence head `9f03f918daa16d63cfbac538b57755289677d35d` passed Dependency Review `31507721516` and CodeQL `31507721523`; CI `31507721345` exposed only unused imports in the new P3 architecture test. The imports were removed without semantic change.

Corrected P3 candidate evidence head: `a67f93706eff4285a229df1f6ce057f2be3b5adc`.

Protected corrected-candidate runs:

- Dependency Review `31508211709` — success.
- CodeQL `31508211738` — success.
- CI `31508211931` — success, including 484 Pint files, PHPStan 345/345 with 0 errors, 375 tests / 7,628 assertions, P3 architecture/link checks, immutable image, staging, backup/restore and image scan.

The final P3 evidence/status state was protected-green at `fee400288908ea052c08ffec9e47a4602806fc56` under Dependency Review `31508943768`, CodeQL `31508943769`, and CI `31508943778`.

After the status file was accidentally overwritten during PR-metadata cleanup, its intended content was restored and the branch reset to clean restored head `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171`, which independently passed Dependency Review `31509458853`, CodeQL `31509458770`, and CI `31509458758`. That is the authoritative P3→P4 transition evidence.

## DCP-P4 accepted candidate evidence

P4 introduced:

- [Interface documentation standard](interface-documentation-standard.md);
- [Interface coverage matrix](interface-coverage-matrix.md);
- [DCP-P4 exit report](interface-completeness-exit-report.md);
- 14 living `docs/domains/<domain>/interfaces/README.md` profiles;
- 2 new focused interface contracts;
- accepted P1 capability-contract reuse indexed from owning profiles;
- canonical domain/product navigation; and
- `tests/Architecture/InterfaceDocumentationTest.php`.

P4 content candidate: `3ebd2ec3a25432baa636840911995be1a451f9c2`.

Validated candidate/evidence head: `66b2ca498ac89e550d3e718b174e07172e7409bd`.

Protected candidate runs:

- Dependency Review `31512996437` — success;
- CodeQL `31512996420` — success;
- CI `31512996421` — success:
  - frontend quality/build — success;
  - PostgreSQL migrations — success;
  - Pint — **485 files**;
  - PHPStan/Larastan — **345/345, 0 errors**;
  - ParaTest/PHPUnit — **381 tests / 8,290 assertions**;
  - P4 profile/focused/reuse/route/bootstrap assertions — success;
  - repository-wide local Markdown-link validation — success;
  - immutable image build — success;
  - ephemeral staging — success;
  - backup/restore — success; and
  - image scan — success.

### P4 accepted content scope

- 14/14 interface profiles.
- New focused contracts:
  - Contributions — `interfaces/report-exports.md`;
  - Events — `interfaces/calendar-exports.md`.
- Reused accepted contracts:
  - Content `media.md`;
  - Contributions `event-reconciliation.md`;
  - Events `registration-and-attendance.md`;
  - Identity `mfa-and-recovery.md`;
  - Integrations `api.md`, `webhooks.md`;
  - Kingdoms `csv-migration.md` plus accepted Kingdoms capability set;
  - Memberships `invitations.md`;
  - Platform `lifecycle-and-retention.md`, `transactional-outbox.md`;
  - Recruitment `application-intake.md`.

Key interface boundaries remain explicit: Kingdoms has no public API/webhook; wildcard webhooks cannot bypass Kingdoms external exclusion; Event calendar files are authenticated rather than public bearer feeds; Contributions manager exports are distinct from external API JSON; and Rallies retains semantic ownership despite Event-controller adapters.

## Final P4 transition gate

This ledger is the last repository-content change required to record P4 completion and select P5.

Before P5 implementation begins, the exact branch head produced by this update must pass:

1. protected Dependency Review;
2. protected CodeQL; and
3. complete CI, including frontend, PHP/documentation architecture/link tests, immutable image, staging, backup/restore, and image scan.

If any of those checks fail, P4 remains the effective current phase and only the final-gate defect may be repaired. If all pass, P4 is fully closed and P5 becomes authoritative without another repository-content transition commit.

## `continue` procedure

On `continue`:

1. Verify the exact final P4 evidence/status head is protected-green.
2. If it is not, remain in P4 and repair only the final-gate defect.
3. If it is green, treat P5 as authoritative and evaluate P5 against the [Documentation completeness standard](documentation-completeness-standard.md) and [program plan](documentation-program-plan.md).
4. Freeze the P5 testing/evidence/traceability inventory before broad implementation.
5. Never advance around an incomplete protected gate.

The detailed definition of `Complete` is normative in [Documentation completeness standard](documentation-completeness-standard.md).
