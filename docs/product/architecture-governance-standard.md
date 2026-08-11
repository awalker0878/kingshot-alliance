# Architecture and program-governance standard

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation

## 1. Purpose

This standard defines how repository-wide architecture and program governance are documented after domain-level contracts, security, operations, interfaces, and validation traceability are complete.

P6 does not create a second implementation plan or duplicate domain contracts. It provides a stable system-level view that links the existing sources of truth and makes cross-domain direction, ADR lifecycle, shared terminology, current-state navigation, and historical boundaries explicit.

## 2. Authority model

Use the narrowest authoritative source that owns the question:

1. accepted implementation-plan baseline and approved named post-baseline product scopes define approved product scope;
2. accepted ADRs define durable architecture decisions and rationale;
3. current code plus architecture/behavior tests define exact implemented runtime structure and behavior;
4. living domain contracts define business/runtime ownership and supported domain contracts;
5. domain security, operations, interface, and testing profiles define those specialized current views;
6. current program architecture views, capability navigation, dependency maps, and audits summarize the system without overriding owners;
7. accepted phase/increment/DCP evidence records prove historical acceptance at their recorded immutable revision.

A system-level summary must link to the owning source rather than copy deep domain implementation detail.

## 3. Required P6 living architecture surfaces

P6 maintains these current program-level artifacts:

- `docs/adr/README.md` — current architecture view plus ADR index/lifecycle;
- `docs/product/cross-domain-dependency-map.md` — current supported dependency/collaboration graph;
- `docs/product/glossary.md` — shared terms whose ambiguity could change architecture/security meaning;
- `docs/product/current-capability-matrix.md` — current implemented capability and explicit non-capability navigation;
- `docs/product/repository-structure-audit.md` — current physical-structure conformance evidence;
- `docs/product/domain-boundary-audit.md` — current semantic ownership/boundary evidence; and
- `docs/product/architecture-governance-coverage-matrix.md` — frozen P6 completion inventory.

## 4. ADR lifecycle

ADR status vocabulary is exactly:

- **Proposed** — decision is under review and is not architecture authority yet;
- **Accepted** — decision is current architecture authority;
- **Superseded** — decision was once accepted but has been replaced by another ADR;
- **Rejected** — proposal was considered and intentionally not adopted.

Accepted and superseded ADRs are immutable historical decision records except for factual navigation/metadata repairs. Do not silently rewrite an old decision's rationale to match newer architecture.

When one ADR replaces another:

- the replacing ADR names the superseded ADR;
- the older ADR status changes to `Superseded` and links to the replacement;
- the ADR index identifies which decision is current; and
- implementation/docs/tests change in the same accepted change or an explicitly linked implementation sequence.

An ADR records architecture, not unapproved product scope. Scope expansion still requires the applicable product approval/acceptance mechanism.

## 5. Cross-domain dependency rule

Dependency notation is **consumer → owning contract**.

A dependency is supported when the consumer relies on an intentional owner surface such as:

- action/application service;
- query/projection service;
- value object or enum;
- domain event or outbox contract;
- explicitly supported model/reference contract where the owning domain permits it; or
- documented first-party integration adapter.

Cross-domain imports are not automatically defects. Persistence reach-through, duplicated ownership, hidden global tenant state, or treating another domain's internal row as locally owned state are defects.

Bidirectional collaboration is allowed when two domains participate in one workflow and ownership remains explicit. A cycle in the high-level collaboration graph is therefore not itself a layering violation.

Do not use raw import/class counts as architecture truth. Counts are diagnostics; ownership and supported contracts are the architecture.

## 6. Shared versus domain-owned documentation

Top-level ownership remains:

- `docs/product/` — cross-program scope/governance/current-state navigation, historical phase-wide acceptance, DCP standards/evidence, architecture audits, production decision records;
- `docs/security/` — shared security policy, phase-wide historical threat evidence, production security boundary;
- `docs/operations/` — shared runtime, deployment, observability, release/recovery runbooks, phase-wide operating evidence;
- `docs/adr/` — durable architecture decisions and current system architecture index.

A document belongs under `docs/domains/<domain>/` when its primary purpose is to describe one domain's business behavior, implementation contract, security/privacy behavior, operations, interfaces, validation map, or domain-specific acceptance evidence.

Cross-domain summaries may mention domains extensively; they must not become a substitute owner for those domain details.

## 7. Current-state versus historical narrative

Current living records describe the repository as it exists now. Historical phase/increment/acceptance records describe what was approved or validated at a recorded point in time.

Allowed maintenance of historical evidence:

- fix broken navigation;
- append recovered immutable identity or factual errata clearly labeled as later hardening;
- mark supersession/obsolescence without deleting the original meaning.

Do not:

- rewrite old test counts as current counts;
- update historical "next phase" language as though the old record were living guidance;
- convert an accepted historical assumption into current truth when later accepted work changed it; or
- delete evidence merely because a living contract now exists.

## 8. Current architecture audit rules

`repository-structure-audit.md` and `domain-boundary-audit.md` are current evidence, not frozen migration reports.

Each must:

- state `Status: Current`;
- identify the current architecture scope being summarized;
- link normative owners;
- distinguish current facts from preserved historical context;
- avoid stale statements that contradict accepted increments; and
- be refreshable against an exact protected-green P6 candidate/final revision.

## 9. Capability/status navigation

`current-capability-matrix.md` is the program-level current-state entry point for implemented capability and explicit non-capability.

It must:

- identify primary owner(s);
- link living contracts;
- distinguish Implemented, Accepted named increment, Not implemented, and Not yet approved states;
- keep repository acceptance separate from real-production approval; and
- avoid becoming a detailed feature specification.

## 10. Shared terminology

Add a glossary term only when inconsistent use can change ownership, authorization, tenancy, lifecycle, evidence, integration, or product-status meaning.

The glossary is normative for shared vocabulary but does not redefine a domain model owned by a domain contract.

Where a general English word conflicts with a product/domain term, use the explicit qualified form in system-level docs. Important examples include platform `Alliance` versus game-side `KingdomAlliance`, global identity versus Alliance membership, internal outbox event versus externally eligible webhook event, and repository acceptance versus production approval.

## 11. Obsolete narrative classification

P6 classifies old narrative as one of:

- **Current** — still authoritative/living;
- **Historical evidence** — retained because it proves a past decision/acceptance;
- **Superseded** — replaced by a newer named architecture decision or living owner;
- **Obsolete duplicate** — no unique evidence or ownership value and safe to remove.

Do not remove historical acceptance evidence merely to reduce file count. Remove only duplicate living narrative whose authority is now unambiguously owned elsewhere.

## 12. P6 validation

P6 architecture tests should enforce stable, high-signal rules only:

- required P6 living artifacts exist;
- ADR filenames/index entries/status vocabulary are valid;
- every canonical code domain appears exactly once in the dependency inventory;
- dependency inventory links to each code-local/living domain owner;
- shared top-level README ownership statements remain program/shared rather than claiming single-domain authority;
- current architecture audits no longer carry migration-candidate status wording;
- current capability/status navigation links the P6 architecture surfaces; and
- local Markdown links remain valid through repository-wide documentation checks.

Do not build brittle CI that parses every code import or forces documentation churn for harmless refactors.

## 13. Change obligations

When architecture changes materially:

1. update or create the governing ADR when durable rationale is required;
2. update the owning domain contract and specialized profiles;
3. update the cross-domain dependency map when ownership/collaboration direction changes;
4. update current capability/status navigation when capability state changes;
5. refresh affected architecture audits;
6. update glossary terms only if shared meaning changed; and
7. change architecture tests with the accepted architecture.

Historical acceptance records remain evidence unless the change is specifically an evidence correction/hardening action.
