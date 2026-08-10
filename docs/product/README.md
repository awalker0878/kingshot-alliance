# Product and program documentation

[← Documentation home](../README.md)

This directory owns the completed Phase 0–6 baseline, approved post-program product increments, current capability/status navigation, architecture/documentation governance evidence, increment/phase acceptance, accessibility evidence, and production-readiness/approval state.

## Authoritative current records

- [Implementation plan](implementation-plan.md) — approved Phase 0–6 baseline, canonical repository structure, delivery governance, and program definition of done.
- [Documentation standard](documentation-standard.md) — **Current** normative docs structure, naming, standard formats, one-domain/one-folder rule, migration record, and CI contract.
- [Current capability matrix](current-capability-matrix.md) — present-tense implemented capabilities and explicit non-capabilities/boundaries.
- [Definition of done](definition-of-done.md) — repository-level completion expectations.
- [Repository structure audit](repository-structure-audit.md) — current physical repository/docs structure evidence and historical refactor context.
- [Domain boundary audit](domain-boundary-audit.md) — current semantic ownership/cross-domain contract evidence.
- [Production hardening exit report](production-hardening-exit-report.md) — **Accepted** repository-controlled hardening evidence.
- [Production launch approval](production-launch-approval.md) — **Not yet approved** for real production cutover until required external/deployment evidence is recorded.
- [Phase 6 launch readiness](phase-6-launch-readiness.md) — historical Phase 6 launch-control expectations feeding current production approval.

Use the capability matrix to answer what exists now. Use the implementation plan for the completed baseline and approved increment scopes for post-program scope. Use living domain folders under [`../domains/`](../domains/README.md) for current business/runtime contracts. Use production launch approval to answer whether real cutover is authorized.

## Accepted post-program Kingdoms increments

| Scope ID | Increment | Status | Outcome |
| --- | --- | --- | --- |
| `KINGDOMS-001` | [Kingdoms roster intelligence](kingdoms-roster-intelligence-increment.md) | **Accepted** | First-class Kingdom/game-player model, Alliance-owned roster, snapshots, controlled CSV migration/export, descriptive roster intelligence. |
| `KINGDOMS-002` | [Kingdoms transfer planning](kingdoms-transfer-planning-increment.md) | **Accepted** | Alliance-owned transfer cycles, participant intent/destinations, groups/coordinators, manual readiness/blockers, explicit roster handoff. |
| `KINGDOMS-003` | [Kingdom/Alliance intelligence and diplomacy](kingdoms-alliance-intelligence-increment.md) | **Accepted** | Neutral game-Alliance identity/tracking, factual observations/history, explicit diplomacy/NAP lifecycle, manager-private contacts, descriptive intelligence. |

### KINGDOMS-001 records

- [Implementation plan](kingdoms-roster-intelligence-implementation-plan.md)
- [Exit report](kingdoms-roster-intelligence-exit-report.md)
- [Accessibility review](kingdoms-roster-intelligence-accessibility.md)
- Living domain contracts: [Kingdoms](../domains/kingdoms/README.md), [Roster](../domains/kingdoms/roster.md), [Snapshots](../domains/kingdoms/snapshots.md), [Intelligence](../domains/kingdoms/intelligence.md), [CSV migration](../domains/kingdoms/csv-migration.md)

### KINGDOMS-002 records

- [Implementation plan](kingdoms-transfer-planning-implementation-plan.md)
- [Exit report](kingdoms-transfer-planning-exit-report.md)
- [Accessibility review](kingdoms-transfer-planning-accessibility.md)
- Living domain contract: [Transfer planning](../domains/kingdoms/transfer-planning.md)

### KINGDOMS-003 records

- [Implementation plan](kingdoms-alliance-intelligence-implementation-plan.md)
- [K3-P0 decisions](kingdoms-alliance-intelligence-p0-decisions.md)
- [Slice A validation](kingdoms-alliance-intelligence-slice-a-validation.md)
- [Slice B validation](kingdoms-alliance-intelligence-slice-b-validation.md)
- [Slice C1 validation](kingdoms-alliance-intelligence-slice-c1-validation.md)
- [Slice C2 validation](kingdoms-alliance-intelligence-slice-c2-validation.md)
- [Slice D validation](kingdoms-alliance-intelligence-slice-d-validation.md)
- [Accessibility review](kingdoms-alliance-intelligence-accessibility.md)
- [Exit report](kingdoms-alliance-intelligence-exit-report.md)
- Living domain contract: [Alliance intelligence and diplomacy](../domains/kingdoms/alliance-intelligence.md)

Candidate follow-ons named inside accepted scopes—such as automated game-data ingestion or opt-in shared Kingdom intelligence—are not themselves approved until they receive an explicit scope/acceptance process.

## Phase acceptance history

The baseline implementation plan ends at Phase 6. Accepted delivery evidence is retained in:

- [Phase 0 exit report](phase-0-exit-report.md)
- [Phase 1 exit report](phase-1-exit-report.md)
- [Phase 2 exit report](phase-2-exit-report.md)
- [Phase 3 exit report](phase-3-exit-report.md)
- [Phase 4 exit report](phase-4-exit-report.md)
- [Phase 5 exit report](phase-5-exit-report.md)
- [Phase 6 exit report](phase-6-exit-report.md)

These are historical acceptance records. Navigation/path maintenance is allowed; do not rewrite old evidence to make it sound like a current changelog.

## Supporting product evidence

- [Phase 3 scope](phase-3-scope.md)
- [Phases 1–4 alignment audit](phases-1-4-alignment-audit.md)
- [Phase 1 accessibility review](phase-1-accessibility-review.md)
- [Phase 2 accessibility](phase-2-accessibility.md)
- [Phase 3 accessibility](phase-3-accessibility.md)
- [Phase 4 accessibility](phase-4-accessibility.md)
- [Phase 5 accessibility](phase-5-accessibility.md)
- [Phase 6 accessibility](phase-6-accessibility.md)

Use current capability/launch records for present-tense status; use historical phase/increment records for the evidence they were created to preserve.

## Status vocabulary

Use status terms consistently:

- **Planned** — approved scope exists but runtime implementation has not started; design gates may already be complete.
- **In progress** — runtime implementation/evidence is being produced.
- **Candidate** — implementation is complete enough for final protected validation but gate not yet passed.
- **Validated** — defined slice/evidence gate passed on the recorded implementation.
- **Accepted** — repository/product completion gate passed and evidence recorded.
- **Approved** — an accountable owner explicitly approved scope or external/production decision; approval does not imply implementation is complete.
- **Not yet approved / Pending** — required evidence/accountable approval remains outstanding.

Do not use **Accepted** and **Approved** interchangeably. K1–K3 are Approved scopes with Accepted implementations. Repository production hardening is Accepted while real production launch remains Not yet approved.

## Documentation architecture

The code/document relationship is deterministic:

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

Capability files stay inside the owning domain folder. `docs/domains/README.md` is the only Markdown file directly under `docs/domains/`. The structure is enforced by `tests/Architecture/RepositoryStructureTest.php` in normal CI.

The former flat domain guides were fully migrated and removed; the authoritative mapping/history is recorded in the [documentation standard](documentation-standard.md).

## Updating program state

When a post-program increment is proposed or delivered:

1. Create/update a named increment scope with stable ID, ownership, boundaries, dependencies, security/operational requirements, acceptance criteria, and explicit deferrals.
2. Create a gated implementation plan when multiple independently reviewable stages are required.
3. Do not create a new numbered program phase unless the baseline implementation plan itself is deliberately reopened/re-approved.
4. Update the capability matrix and living domain folders so implemented slices, accepted work, and unapproved candidates remain distinguishable.
5. When implementation closes, create an increment-specific exit/acceptance record with exact validated head/protected evidence.
6. Preserve historical phase reports rather than rewriting them into current-state prose.
7. Record deferred work explicitly without partially documenting it as present capability.
8. Keep documentation aligned with the [documentation standard](documentation-standard.md) and required architecture CI rules.

There is no Phase 7 in the current baseline. `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` are accepted post-program increments, not continuation of phase numbering. Real production cutover remains separately governed.
