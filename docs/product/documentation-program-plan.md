# Documentation Completion Program

[← Product and program documentation](README.md)

**Document type:** Program implementation plan  
**Status:** Current  
**Program ID:** `DCP`  
**Applies to:** Repository documentation standards, domain documentation, shared product/security/operations documentation, architecture documentation, and documentation CI enforcement

## 1. Objective

The Documentation Completion Program turns the domain-first documentation ownership model into a complete, governed, maintainable documentation system.

The baseline remains:

- code ownership determines domain documentation ownership;
- `docs/domains/<domain>/` owns current domain contracts and domain-specific evidence;
- top-level product/security/operations documentation remains cross-domain/program-wide;
- `docs/adr/` owns durable architecture decisions and the current system architecture entry point; and
- code/tests remain authoritative for exact implemented runtime behavior.

DCP does not create a parallel product phase sequence. Product Phase 0–6 and accepted post-baseline increments retain their own historical evidence. `DCP-P0` through `DCP-P7` are documentation-governance phases.

## 2. Program gate

Every DCP phase is hard-gated by [Documentation completeness standard](documentation-completeness-standard.md).

A phase advances only when:

1. its required inventory is frozen;
2. 100% of required content is complete;
3. current navigation/source-of-truth rules are satisfied;
4. applicable architecture/documentation validation passes on an exact candidate head;
5. exact protected Dependency Review, CodeQL, and complete CI pass where required; and
6. the resulting final evidence/status transition head independently passes the protected gate before the next phase becomes authoritative.

The user command `continue` means exactly one of:

- finish the current incomplete phase; or
- advance only because the current phase is fully complete.

It never skips a gate. Current control state is recorded in [Documentation program status](documentation-program-status.md).

## 3. Program standards catalog

Base standards:

- [Repository documentation standard](documentation-standard.md) — structure, ownership, naming, base formats, and source-of-truth rules;
- [Documentation completeness standard](documentation-completeness-standard.md) — document/coverage/phase completion and exact-gate rules.

Specialized standards:

| Standard | Primary phase | Purpose |
| --- | --- | --- |
| [`domain-contract-standard.md`](domain-contract-standard.md) | `DCP-P1` | Domain/code maps, living contract depth, capability splitting, cross-domain contracts. |
| [`security-documentation-standard.md`](security-documentation-standard.md) | `DCP-P2` | Security/privacy/trust/data-protection documentation. |
| [`operations-documentation-standard.md`](operations-documentation-standard.md) | `DCP-P3` | Runtime state, diagnostics, recovery, replay, capacity, migration/rollback. |
| [`interface-documentation-standard.md`](interface-documentation-standard.md) | `DCP-P4` | HTTP/UI/API/events/jobs/commands/files/import/export/integration contracts. |
| [`testing-evidence-standard.md`](testing-evidence-standard.md) | `DCP-P5` | Validation maps, evidence identity, performance/migration/accessibility traceability and retention. |
| [`architecture-governance-standard.md`](architecture-governance-standard.md) | `DCP-P6` | ADR lifecycle, cross-domain dependencies, glossary, current architecture and shared governance. |
| [`documentation-maintenance-standard.md`](documentation-maintenance-standard.md) | `DCP-P7` | Change-time obligations, drift prevention, CI enforcement, review and archival lifecycle. |

Standards are program-wide under `docs/product/`; documents created under them remain with their actual owner.

## 4. Phase model

### `DCP-P0` — Governance and continuation controls

**Goal:** establish program plan, completeness rules, status ledger, navigation, and deterministic `continue` semantics.

**Exit:** program controls are unambiguous and discoverable.

### `DCP-P1` — Domain contract and code-ownership completeness

**Goal:** prove every canonical code domain has a complete code-local map, living domain contract, and all material capability contracts.

**Exit:** 14/14 code-local READMEs, 14/14 canonical domain contracts, complete material-capability inventory, owner/link validation, and protected phase evidence.

### `DCP-P2` — Security, privacy, and data-protection completeness

**Goal:** complete applicable security/privacy/trust/retention/abuse documentation for every domain while keeping shared security genuinely cross-domain.

**Exit:** 14/14 domain security profiles, all required focused reviews, correct evidence placement, and protected phase evidence.

### `DCP-P3` — Operations, reliability, and recovery completeness

**Goal:** document persisted/async runtime state, diagnostics, replay/reconciliation, backup/recovery, rollback, capacity, degradation, and operator safety.

**Exit:** complete domain operations profiles/focused runbooks, consistent shared operations, and protected phase evidence.

### `DCP-P4` — Interfaces, events, and integrations completeness

**Goal:** make every material browser/API/CLI/job/event/webhook/file/import/export/external boundary discoverable with an owner and contract-level documentation.

**Exit:** complete domain interface profiles/focused or reused compatibility contracts, public/internal boundaries explicit, and protected phase evidence.

### `DCP-P5` — Testing, evidence, and traceability completeness

**Goal:** make critical documented claims traceable to suitable executable/operational/immutable evidence without turning living docs into test logs.

**Exit:** complete domain validation maps, exact six-suite evidence taxonomy, immutable acceptance identity rules, historical evidence audit/hardening, and protected phase evidence.

### `DCP-P6` — Architecture and program-governance consolidation

**Goal:** make the complete documented system understandable at repository level after domain completeness is proven.

**Exit:** current ADR lifecycle/index, 14-domain dependency map, shared glossary, current audits/capability/navigation, shared ownership reconciliation, architecture-governance enforcement, and protected candidate/final evidence.

### `DCP-P7` — Maintenance automation and final acceptance

**Goal:** prevent documentation completeness from degrading after the program finishes.

Required outputs:

- [Documentation maintenance standard](documentation-maintenance-standard.md);
- final maintenance/final-acceptance inventory;
- normal change-time governance wired into documentation standard and Definition of Done;
- final deterministic architecture/completeness automation over stable P1–P7 rules; and
- final Documentation Completion Program exit evidence.

Final automation should protect stable signals such as metadata/status/index consistency, code/docs/profile parity, ownership/path rules, local links, standards indexing, evidence placement, ADR lifecycle, current architecture navigation, and final maintenance governance.

It must not parse every implementation detail, infer ownership from raw import counts, compare historical evidence against current totals, or require documentation churn for harmless refactors.

**Exit:** every prior phase remains complete, all standards are current/indexed/non-conflicting, maintenance workflow is defined, final architecture/link/completeness automation passes, exact protected candidate/final evidence is recorded, and the final DCP exit record marks the program Complete.

There is no `DCP-P8`. After P7, future documentation work is normal change-driven maintenance under the standards created by DCP.

## 5. Phase execution rules

For every phase:

1. read current status and phase scope;
2. freeze an explicit inventory before claiming coverage complete;
3. adopt the phase standard before broad normalization;
4. complete the whole frozen inventory;
5. route newly discovered required work to the actual owning phase/standard rather than ignoring it;
6. maintain indexes and source-of-truth links as documents change;
7. run applicable automated checks throughout;
8. perform a full inventory review before exit rather than reviewing only recently changed files;
9. record immutable exit evidence; and
10. advance only through the exact protected `continue` gate.

## 6. `continue` reporting

Each `continue` decision should identify:

- current phase;
- gate status;
- finish-versus-advance decision;
- concrete remaining/current work; and
- validation already passed versus still required.

When P7 is fully complete, future `continue` requests no longer advance a DCP phase; documentation changes follow [Documentation maintenance standard](documentation-maintenance-standard.md).

## 7. Program end state

DCP is complete only when:

- every code domain has complete living contracts/capability documentation;
- security/privacy coverage is complete;
- operations/recovery coverage is complete;
- interfaces/integrations are complete;
- testing/evidence traceability is complete;
- system-level architecture/governance navigation is complete;
- specialized standards are current and indexed; and
- CI plus maintenance governance protects the stable rules worth automating.

The resulting repository can be navigated deterministically from code owner to business contract, security/operations/interfaces/testing evidence, cross-domain architecture, historical acceptance, and production decision boundary.
