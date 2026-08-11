# Documentation Completion Program status

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program status ledger  
**Status:** Current  
**Program ID:** `DCP`

## Current control state

**Current phase:** `DCP-P5` — Testing, evidence, and traceability completeness  
**Gate status:** Candidate — 100% frozen content inventory implemented; protected candidate validation pending  
**Control decision on next `continue`:** Finish current phase

DCP-P4 is fully closed. Its final evidence/status head `286847006544d1af2e4dbf2f0211c5f28ad2cb33` passed protected Dependency Review `31513724817`, CodeQL `31513724836`, and CI `31513724840`, including frontend, PHP/documentation checks, immutable image, staging, backup/restore and image scan.

DCP-P5 has completed its frozen content inventory: 14/14 living domain testing/evidence profiles, the six canonical PHPUnit suites and repository quality/protected-workflow evidence classes mapped, Phase 0–6/Kingdoms/DCP acceptance evidence audited, historical Phase 5/6 immutable identities hardened from recovered GitHub evidence, canonical navigation normalized, and deterministic P5 architecture enforcement added.

P5 content candidate is `e49b4c88d7156101a9d9f8351fe8ba42f83a9632`. The exact candidate/evidence head containing this ledger and [P5 exit report](testing-evidence-completeness-exit-report.md) must pass protected Dependency Review, CodeQL, and complete CI before P5 can be finalized. P6 remains blocked.

## Phase ledger

| Phase | Name | Status | Exit decision |
| --- | --- | --- | --- |
| `DCP-P0` | Governance and continuation controls | Complete | Program plan, completeness standard, status ledger and navigation established. |
| `DCP-P1` | Domain contract and code-ownership completeness | Complete | 14 domain maps, 14 canonical contracts, 19 material capability contracts, P1 CI enforcement, and protected validation complete. See [P1 exit report](domain-contract-completeness-exit-report.md). |
| `DCP-P2` | Security, privacy, and data-protection completeness | Complete | 14 domain security profiles, 9 new focused reviews, normalized Kingdoms security evidence, P2 CI enforcement, and protected candidate/final-head validation complete. See [P2 exit report](security-completeness-exit-report.md). |
| `DCP-P3` | Operations, reliability, and recovery completeness | Complete | 14 operations profiles, 6 new focused runbooks, 3 retained Kingdoms guides, P3 CI enforcement, corrected candidate and final-head protected validation complete. See [P3 exit report](operations-completeness-exit-report.md). |
| `DCP-P4` | Interfaces, events, and integrations completeness | Complete | 14 interface profiles, 2 new focused contracts, accepted contract reuse, complete boundary inventory, P4 CI enforcement and protected candidate/final-head validation complete. See [P4 exit report](interface-completeness-exit-report.md). |
| `DCP-P5` | Testing, evidence, and traceability completeness | Candidate | **Current phase.** 100% frozen content inventory implemented; protected candidate/final-head validation remains. See [P5 exit report](testing-evidence-completeness-exit-report.md). |
| `DCP-P6` | Architecture and program-governance consolidation | Not started | Blocked by `DCP-P5`. |
| `DCP-P7` | Maintenance automation and final acceptance | Not started | Blocked by `DCP-P6`. |

## Accepted DCP transition evidence

### DCP-P1

- validated candidate head `be4a87734b44fa09643b6e8e5066283b5ed4fece`;
- protected candidate DR `31500031422`, CodeQL `31500031623`, CI `31500031488` — success;
- final accepted evidence/status head `60357543256478aa8ef8c26f67e27631df8c5ba4`, protected-green.

### DCP-P2

- corrected content candidate `645c943e59439840d3563452d97612eb17d63b10`;
- corrected candidate evidence head `eea41be6bf45820a7f3ab06f57cc24703e7d2b8e`;
- protected candidate DR `31504587302`, CodeQL `31504587346`, CI `31504587198` — success;
- final accepted head `35121bf732f75c72351a7c232548f3e78fb1c8ff` with DR `31505325682`, CodeQL `31505325673`, CI `31505325711` — success.

### DCP-P3

- corrected content candidate `b6f4aa9ca929ff75fef48344423eee7891210d26`;
- corrected candidate evidence head `a67f93706eff4285a229df1f6ce057f2be3b5adc` with DR `31508211709`, CodeQL `31508211738`, CI `31508211931` — success;
- after the documented status-file cleanup/revalidation path, clean transition head `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171` passed DR `31509458853`, CodeQL `31509458770`, CI `31509458758` and is the authoritative P3→P4 transition evidence.

### DCP-P4

- content candidate `3ebd2ec3a25432baa636840911995be1a451f9c2`;
- candidate/evidence head `66b2ca498ac89e550d3e718b174e07172e7409bd` passed DR `31512996437`, CodeQL `31512996420`, CI `31512996421`, including 485 Pint files, PHPStan/Larastan 345/345 with 0 errors, 381 tests / 8,290 assertions, interface/route/link checks, immutable image, staging, backup/restore and scan;
- final evidence/status head `286847006544d1af2e4dbf2f0211c5f28ad2cb33` passed DR `31513724817`, CodeQL `31513724836`, CI `31513724840`.

## DCP-P5 candidate evidence

P5 evidence currently includes:

- [Testing and evidence standard](testing-evidence-standard.md);
- [Testing and evidence coverage matrix](testing-evidence-coverage-matrix.md);
- [DCP-P5 exit report](testing-evidence-completeness-exit-report.md);
- 14 living `docs/domains/<domain>/testing/README.md` profiles;
- canonical domain/product navigation to P5 profiles/governance;
- `tests/Architecture/TestingEvidenceDocumentationTest.php`; and
- historical Phase 5/6 exit-report traceability hardening using recovered immutable GitHub SHA/run identities.

P5 content candidate: `e49b4c88d7156101a9d9f8351fe8ba42f83a9632`.

### Canonical executable evidence baseline

P5 maps exactly six PHPUnit suites from `phpunit.xml`:

- `Architecture` → `tests/Architecture`;
- `Feature` → `tests/Feature`;
- `Integration` → `tests/Integration`;
- `Performance` → `tests/Performance`;
- `TenantIsolation` → `tests/TenantIsolation`;
- `Unit` → `tests/Unit`.

Repository quality/protected evidence classes include `composer check`, `npm run check`, Dependency Review, CodeQL, CI PostgreSQL migrations, immutable production-image build, ephemeral staging, backup/restore and image scan.

### Historical evidence hardening

Phase 5 exit evidence now retains final PR #18 head `c30aaab0ee3b03c65f27042a2700540bdebbf9c4` with DR `31219686800`, CodeQL `31219686802`, CI `31219686960`.

Phase 6 exit evidence now retains implementation head `d1969889ffa044cd7690f263ba9ef70c63a425cb` with DR `31235514849`, CodeQL `31235514858`, CI `31235514843`, plus final PR #19 head `35979623d8231ee56b8fbcb75301e7e0732df0ca` with DR `31252682835`, CodeQL `31252682836`, CI `31252682853`.

These changes are factual traceability hardening only; historical accepted scope, behavior and test counts were not recomputed.

### P5 evidence rules now explicit

- living current validation maps are separate from immutable historical exit records;
- a branch name, PR number or prose saying “CI passed” is not sufficient immutable acceptance identity;
- migration rollback/reapply, database backup/restore, and domain recovery-set evidence are distinct claims;
- frontend quality is not automatically accessibility certification;
- numeric performance/SLA/query claims require executable accepted evidence; and
- historical test counts/check IDs remain historical rather than becoming current repository totals.

## P5 validation gate

Before P5 becomes Complete:

1. protected Dependency Review must pass on the exact candidate/evidence head;
2. protected CodeQL must pass;
3. complete CI must pass, including the P5 architecture/traceability assertions and repository-wide Markdown-link validation;
4. immutable image, staging, backup/restore and image scan must pass where included;
5. exact candidate validation identifiers must be recorded in the P5 exit/status evidence; and
6. the resulting final P5 evidence/status head must independently pass the same protected gate before P6 becomes authoritative.

## `continue` procedure

On `continue`:

1. Treat P5 as authoritative until both protected gates close.
2. If candidate validation exposes a P5 defect, remain in P5 and repair only that defect.
3. If the candidate gate passes, finalize P5 exit/status evidence and select P6 conditionally on the exact final-head gate.
4. Only when that final head is fully protected-green may P6 implementation begin.
5. Never advance around incomplete required documentation or protected evidence.

The detailed definition of `Complete` is normative in [Documentation completeness standard](documentation-completeness-standard.md).
