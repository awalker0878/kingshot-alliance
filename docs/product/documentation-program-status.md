# Documentation Completion Program status

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Program status ledger  
**Status:** Current  
**Program ID:** `DCP`

## Current control state

**Current phase:** `DCP-P4` — Interfaces, events, and integrations completeness  
**Gate status:** In progress — code-backed inventory frozen; required profiles/contracts/CI still being implemented  
**Control decision on next `continue`:** Finish current phase

DCP-P3 is fully closed. The clean final P3 evidence/status branch head `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171` passed protected Dependency Review `31509458853`, CodeQL `31509458770`, and CI `31509458758`, including frontend, PHP/documentation checks, immutable image, staging, backup/restore, and image scan.

DCP-P4 has adopted [Interface documentation standard](interface-documentation-standard.md) and frozen the code-backed [Interface coverage matrix](interface-coverage-matrix.md). The frozen content target is 14/14 living domain interface profiles plus two new focused compatibility-sensitive contracts for Contributions report exports and Events calendar exports, while complete accepted P1 capability contracts are reused instead of duplicated.

P5 remains blocked until P4 reaches 100% inventory coverage and both its exact candidate and exact final evidence/status heads pass the full protected gate.

## Phase ledger

| Phase | Name | Status | Exit decision |
| --- | --- | --- | --- |
| `DCP-P0` | Governance and continuation controls | Complete | Program plan, completeness standard, status ledger and navigation established. |
| `DCP-P1` | Domain contract and code-ownership completeness | Complete | 14 domain maps, 14 canonical contracts, 19 material capability contracts, P1 CI enforcement, and protected validation complete. See [P1 exit report](domain-contract-completeness-exit-report.md). |
| `DCP-P2` | Security, privacy, and data-protection completeness | Complete | 14 domain security profiles, 9 new focused reviews, normalized Kingdoms security evidence, P2 CI enforcement, and protected candidate/final-head validation complete. See [P2 exit report](security-completeness-exit-report.md). |
| `DCP-P3` | Operations, reliability, and recovery completeness | Complete | 14 operations profiles, 6 new focused runbooks, 3 retained Kingdoms guides, P3 CI enforcement, corrected candidate and final-head protected validation complete. See [P3 exit report](operations-completeness-exit-report.md). |
| `DCP-P4` | Interfaces, events, and integrations completeness | In progress | **Current phase.** Standard/inventory frozen; profiles, focused contracts, navigation and P4 CI are being completed. |
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

## DCP-P3 accepted evidence

Corrected P3 content candidate: `b6f4aa9ca929ff75fef48344423eee7891210d26`.

The initial P3 evidence head `9f03f918daa16d63cfbac538b57755289677d35d` passed Dependency Review `31507721516` and CodeQL `31507721523`. CI `31507721345` failed only Pint's `no_unused_imports` check on four unused iterator imports in the new P3 architecture test; the imports were removed without changing documentation or test semantics.

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

The final P3 evidence/status state was protected-green at `fee400288908ea052c08ffec9e47a4602806fc56` under Dependency Review `31508943768`, CodeQL `31508943769`, and CI `31508943778`.

During PR-metadata cleanup the status file was accidentally overwritten; its exact intended content was restored. The branch was reset to clean restored head `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171`, which independently passed:

- Dependency Review `31509458853` — success;
- CodeQL `31509458770` — success; and
- CI `31509458758` — success, including frontend, PHP/documentation checks, immutable image, staging, backup/restore, and image scan.

That protected result is the authoritative P3→P4 transition evidence.

## DCP-P4 frozen inventory

P4 evidence currently includes:

- [Interface documentation standard](interface-documentation-standard.md);
- [Interface coverage matrix](interface-coverage-matrix.md);
- executable route/bootstrap inventory covering `routes/web.php`, `routes/api.php`, `routes/account.php`, `routes/contributions.php`, `routes/integrations.php`, `routes/kingdoms.php`, `routes/platform.php`, `routes/console.php`, and `bootstrap/app.php`;
- custom command/scheduler inventory;
- transactional-outbox/internal-consumer/external-webhook eligibility inventory;
- external machine API credential/scope/endpoint inventory;
- file/import/export/media inventory; and
- explicit significant non-capability inventory.

### Frozen P4 artifact target

- **14/14** `docs/domains/<domain>/interfaces/README.md` living interface profiles.
- **2/2** new focused interface contracts:
  - Contributions — `interfaces/report-exports.md`;
  - Events — `interfaces/calendar-exports.md`.
- Accepted focused capability contracts reused/indexed from owning profiles:
  - Content — `media.md`;
  - Contributions — `event-reconciliation.md`;
  - Events — `registration-and-attendance.md`;
  - Identity — `mfa-and-recovery.md`;
  - Integrations — `api.md`, `webhooks.md`;
  - Kingdoms — `csv-migration.md` and accepted Kingdoms capability set;
  - Memberships — `invitations.md`;
  - Platform — `lifecycle-and-retention.md`, `transactional-outbox.md`;
  - Recruitment — `application-intake.md`.

### P4 public/internal boundary decisions

- Integrations owns the only accepted external machine API and outbound webhook contracts.
- Producer domains own business event meaning; Platform owns durable outbox publication; Integrations independently decides external webhook eligibility.
- `alliance.kingdom_updated` and all `kingdoms.*` events remain externally excluded.
- Kingdoms has no accepted public API/webhook contract.
- Events CSV/ICS and Contributions report exports are authenticated/privileged first-party file contracts, not public APIs.
- Notifications, Audit and Authorization have material internal boundaries but no direct external HTTP API.
- Rallies owns Rally actions/state while its current first-party HTTP adapter surface is mediated through Event controllers/routes.

## P4 remaining work

1. Implement all 14 living interface profiles.
2. Implement the two frozen new focused interface contracts.
3. Index reused accepted capability contracts from the owning profiles.
4. Normalize domain/product navigation.
5. Add deterministic P4 architecture enforcement.
6. Perform a complete frozen-inventory/link review.
7. Record P4 candidate evidence and validate the exact candidate head.
8. If green, finalize P4 exit/status evidence, select P5, and validate the exact final head before P5 work begins.

## `continue` procedure

On `continue`:

1. Treat the phase marked **Current phase** above as authoritative.
2. Evaluate it against the [Documentation completeness standard](documentation-completeness-standard.md), [program plan](documentation-program-plan.md), [Interface documentation standard](interface-documentation-standard.md), and frozen [Interface coverage matrix](interface-coverage-matrix.md).
3. If required P4 documentation remains incomplete, keep P4 active and finish it.
4. If P4 is a complete candidate, run/finalize the protected candidate and exit-evidence gates.
5. Only when P4 is fully complete may this ledger select P5.
6. Never advance around incomplete required documentation.

The detailed definition of `Complete` is normative in [Documentation completeness standard](documentation-completeness-standard.md).
