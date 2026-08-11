# Documentation maintenance standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Phase:** `DCP-P7` — Maintenance automation and final acceptance  
**Applies after DCP completion:** All repository documentation changes and material code changes that affect documented ownership, behavior, risk, operations, interfaces, evidence, or architecture

## 1. Purpose

This standard turns Documentation Completion Program output into normal change-driven maintenance. Its goal is to prevent material documentation drift without requiring documentation churn for harmless implementation refactors.

The repository already has complete owner-specific standards for domain contracts, security, operations, interfaces, testing/evidence, and architecture/governance. P7 does not duplicate those standards. It defines when maintainers must update them, how evidence is retained/superseded, what stable rules CI should enforce, and how final DCP completeness remains protected after the program ends.

## 2. Maintenance principle

Documentation changes are **impact-driven**, not file-count-driven.

A code change requires documentation work when it materially changes one or more of:

- business capability or explicit non-capability;
- code/domain ownership or a supported cross-domain contract;
- authorization, tenant, trust, privacy, retention, or destructive-operation behavior;
- persistent state, scheduler/job/queue/outbox behavior, diagnostics, recovery, rollback, capacity, or operator action;
- HTTP/UI/API/CLI/event/webhook/import/export/media/external-service contract;
- validation strategy, accepted performance bound, migration/accessibility evidence, or immutable acceptance identity;
- durable architecture direction, ADR status, shared terminology, or current program navigation; or
- production configuration/evidence/approval boundary.

An internal refactor that leaves all of those contracts unchanged normally requires no prose update merely because classes, methods, or imports moved internally.

## 3. Change classification and required documentation

| Material change | Required owner updates |
| --- | --- |
| Domain ownership, model, invariant, lifecycle, public supported contract | code-local `app/Domain/<Domain>/README.md`; canonical `docs/domains/<domain>/README.md`; focused capability docs as applicable; dependency map/audits if direction changes |
| Security/privacy/tenancy/authentication/authorization/destructive behavior | owning domain `security/README.md`; focused security review where complexity warrants; testing profile/evidence when validation changes; shared security only for genuinely cross-domain policy |
| Runtime state, queue/scheduler/outbox, diagnostics, recovery, rollback, capacity/degradation | owning domain `operations/README.md` or focused runbook; shared operations only for shared runtime/process changes; testing profile if evidence changes |
| Browser/UI/API/CLI/event/job/webhook/import/export/media/external integration | owning `interfaces/README.md` and focused interface contract where compatibility-sensitive; update Integrations boundary when exposure changes; testing profile/evidence as applicable |
| Test/evidence strategy, explicit performance/query bound, migration/accessibility acceptance | owning `testing/README.md`; current evidence navigation; immutable acceptance record only for an actual acceptance gate |
| Durable architecture direction | create/update ADR; update dependency map, architecture index/audits/glossary/current capability as applicable; domain owners remain authoritative for detail |
| Product capability/status or named increment | current capability matrix; approved domain/program scope/evidence owner; explicit non-capability when intentionally absent |
| Shared runtime/security/production control | shared operations/security current owner plus production approval/hardening records as applicable |
| Documentation structure/ownership/standard | documentation standard, affected indexes, architecture tests, and this maintenance standard if obligations change |

Update only applicable rows. One material change may touch multiple rows when it crosses concerns.

## 4. Domain change obligations

For every material domain change:

1. preserve `app/Domain/<Domain>` ↔ `docs/domains/<domain>` ownership parity;
2. update the code-local README when owned code/public contracts/dependencies change materially;
3. update the canonical domain contract when business/runtime behavior changes materially;
4. update the applicable security/operations/interfaces/testing profile when that concern changes;
5. update focused capability/review/runbook/interface/evidence documents only when the change belongs there;
6. update cross-domain dependency/navigation only when system-level direction or capability state changes; and
7. remove obsolete duplicate living narrative instead of leaving conflicting current sources.

Do not create a new top-level documentation group for a feature domain.

## 5. Interface and compatibility obligations

A material externally observable or compatibility-sensitive change requires explicit documentation before acceptance.

Examples:

- API route/scope/payload/limit/error semantics;
- webhook eligibility/envelope/signature/retry/version semantics;
- CSV/SpreadsheetML/iCalendar/import schema semantics;
- invitation/application/bearer token semantics;
- public/member/manager/admin disclosure differences;
- media/storage/download behavior; and
- command/job/scheduler/operator interfaces relied on outside one implementation class.

Internal events or outbox messages do not automatically become public contracts. External exposure still requires the owning Integrations/interface approval path.

## 6. Security, privacy, and operations obligations

When a change affects sensitive data, tenant boundaries, secret/token handling, privileged workflows, deletion/retention, or external trust boundaries, update the owning security documentation and executable evidence in the same accepted change.

When a change affects recoverable state or asynchronous work, update operations documentation so that failure diagnosis, replay/idempotency, reconciliation, backup/restore, rollback, and operator stop conditions remain current.

Never document repository-controlled CI proof as evidence of external production controls that the repository cannot observe.

## 7. Architecture and ADR maintenance

Create an ADR when a durable architecture decision changes system direction, ownership, deployment/runtime architecture, cross-domain collaboration rules, major technology strategy, or a previously accepted decision.

Use lifecycle values exactly:

- Proposed;
- Accepted;
- Superseded;
- Rejected.

When an ADR is superseded, preserve its rationale, mark it Superseded, link the replacing ADR, and update current architecture/dependency/audit surfaces. Do not rewrite the old rationale as though the replacement had always been true.

Routine implementation decisions that do not change durable architecture do not require a new ADR.

## 8. Evidence lifecycle

Living current documents and immutable historical evidence have different maintenance rules.

### Living current documents

Update when current implemented behavior, ownership, risk, operations, interfaces, validation mapping, architecture, or status changes.

### Historical acceptance evidence

Preserve the accepted meaning and recorded revision. Allowed later maintenance is limited to:

- broken-navigation repair;
- clearly labeled factual errata;
- clearly labeled recovered immutable SHA/check identity; and
- supersession/obsolescence pointers that do not rewrite the original acceptance result.

Do not recompute historical test counts or replace historical workflow IDs with current ones.

## 9. Status vocabulary maintenance

Use status vocabularies only in their owning context:

- DCP/documentation work: `Not started`, `In progress`, `Blocked`, `Candidate`, `Complete`;
- ADR lifecycle: `Proposed`, `Accepted`, `Superseded`, `Rejected`;
- product/release state: `Planned`, `In progress`, `Candidate`, `Validated`, `Accepted`, `Approved`, `Not implemented`, `Not yet approved` / `Pending` as defined by current program guidance.

Do not treat `Accepted` and `Approved` as synonyms. Repository acceptance does not approve real production launch.

Historical records may contain old status language appropriate to their recorded context; automation should target current normative/control records rather than rewriting history.

## 10. Navigation and link obligations

When creating, moving, renaming, superseding, or removing a current primary document:

- update its owning index;
- update repository/product/domain navigation that directly points to it;
- preserve a historical evidence path only when it has independent evidence value;
- do not retain empty compatibility/stub files solely for old internal paths; and
- keep repository-relative Markdown links valid.

The five top-level documentation groups remain exactly `adr`, `domains`, `operations`, `product`, and `security`.

## 11. Review and archival lifecycle

Current living documents should be reviewed as part of the material change that affects them; no calendar-based rewrite is required when behavior has not changed.

Classify stale material before removal:

- **Current** — still authoritative;
- **Historical evidence** — retained for acceptance/decision traceability;
- **Superseded** — replaced by a named current authority but retained when it has decision/evidence value;
- **Obsolete duplicate** — no unique evidence or ownership value; remove it.

Archival is an ownership decision, not a substitute for fixing current navigation.

## 12. CI automation principles

Final documentation automation must be deterministic and high-signal. It should protect stable architecture/governance properties such as:

- canonical top-level groups;
- code-domain/docs-domain parity;
- required domain profile parity;
- code-local→canonical documentation linkage;
- local Markdown links;
- primary index/navigation links;
- standards catalog/index consistency;
- allowed current status vocabularies;
- domain-specific evidence placement boundaries;
- ADR lifecycle/index rules; and
- final DCP/current maintenance governance artifacts.

Automation should **not**:

- parse every method/class/import as documentation scope;
- require prose edits for harmless refactors;
- infer business ownership from raw dependency counts;
- validate historical records against current test totals; or
- force one document per endpoint/test/class.

## 13. Final DCP completeness protection

After DCP-P7 completes, CI must continue running the stable P1–P7 architecture/documentation tests. A later change that breaks one of those gates must either:

1. repair the drift while preserving the accepted architecture; or
2. intentionally change the applicable architecture/documentation standard and its enforcement in the same reviewed change.

Do not weaken a gate merely to permit undocumented drift.

## 14. Pull request review checklist

For a material change, reviewers should be able to answer:

- What domain/shared area owns the changed behavior?
- Did the relevant living contract/profile change if the contract changed?
- Did cross-domain direction or external compatibility change?
- Is historical evidence preserved rather than rewritten?
- Are capability/status/ADR/current architecture surfaces still correct?
- Do links/indexes resolve?
- Do all applicable documentation architecture tests pass on the exact final head?
- Is production approval still represented separately from repository acceptance?

A change with no documentation update should be defensible because no documented contract materially changed, not because documentation was forgotten.

## 15. Post-program maintenance state

After the final DCP exit gate, there is no `DCP-P8`. Future documentation work is normal repository maintenance under:

- [Repository documentation standard](documentation-standard.md);
- [Documentation completeness standard](documentation-completeness-standard.md) for any future explicit completeness program/gate;
- P1–P6 specialized standards;
- this maintenance standard; and
- the repository [Definition of Done](definition-of-done.md).

The DCP status/exit records remain historical program evidence while current standards, domain contracts, architecture, capability, security, operations, interfaces, and validation maps continue to evolve with accepted changes.
