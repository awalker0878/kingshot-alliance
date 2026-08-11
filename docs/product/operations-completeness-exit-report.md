# DCP-P3 operations, reliability, and recovery completeness exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P3` — Operations, reliability, and recovery completeness  
**Status:** Candidate — protected validation pending  
**Content candidate SHA:** `55dd2d29cb1c45dd3c01e9e42f6b57a8a9118c3d`

## 1. Outcome

The DCP-P3 operations/reliability/recovery content inventory is fully implemented and ready for protected validation.

P3 does not advance to DCP-P4 until the exact candidate/evidence head passes protected Dependency Review, CodeQL, and the complete CI workflow, and the resulting final exit/status head also passes the same protected gate.

## 2. Standard adopted

P3 introduced [Operations documentation standard](operations-documentation-standard.md), defining:

- source-of-truth precedence across runtime code/configuration, shared living operations, domain operations and historical evidence;
- mandatory living `docs/domains/<domain>/operations/README.md` profiles for every canonical domain;
- a deterministic 12-section operations-profile format;
- a risk-based threshold for focused living capability runbooks;
- a deterministic 10-section focused-runbook format;
- replay/reconciliation versus restart/rollback/restore distinctions;
- recovery-set, capacity, degradation, stop-condition and evidence rules; and
- high-signal P3 CI enforcement.

## 3. Frozen inventory result

The [Operations coverage matrix](operations-coverage-matrix.md) covers all 14 canonical domains.

Implemented coverage:

- **14/14** living domain operations profiles;
- **6/6** new focused living operations runbooks required by the frozen P3 inventory; and
- the three accepted Kingdoms K1–K3 operations guides retained and indexed from the normalized Kingdoms operations profile.

## 4. New focused living runbooks

P3 added focused runbooks for independently complex operational boundaries:

- Content — scheduled publication plus private media/scanner/object-storage recovery;
- Integrations — durable signed webhook delivery through outbox, Redis/Horizon and external HTTP/DNS/egress;
- Notifications — recurring Event-reminder and Contribution-report materialization/queueing;
- Platform — shared transactional outbox publication/retry/reconciliation;
- Platform — account/Alliance lifecycle, usage, legal hold, retention and anonymization; and
- Recruitment — scheduled candidate retention/anonymization.

Alliances, Audit, Authorization, Contributions, Events, Identity, Memberships and Rallies were explicitly reviewed as profile-only domains. Contributions and Events point to Notifications for the scheduler-owned coordination path instead of duplicating its recovery authority.

## 5. Kingdoms operations normalization

`docs/domains/kingdoms/operations/README.md` is now the mandatory current P3 operations profile.

It retains the accepted domain-owned guides for roster intelligence, transfer planning and Alliance intelligence instead of cosmetically rewriting their accepted formats. The profile makes current shared-runtime dependencies, synchronous/no-ingestion runtime shape, recovery/reconciliation rules, query-performance evidence boundary and prohibited automation shortcuts explicit.

## 6. Shared runtime reconciliation

P3 preserves the existing shared living operations contracts as cross-domain authority:

- `background-processing.md` for scheduler/Horizon/outbox behavior;
- `configuration-reference.md` for hosted runtime dependencies;
- `observability.md` for health/correlation/diagnostic signals;
- shared deployment, rollback, backup/restore and incident-response runbooks; and
- production launch/release controls.

Domain profiles consume these shared contracts and add only domain-owned persisted-state semantics, domain-specific diagnosis/recovery, verification and stop conditions.

## 7. Recovery and rollback completeness

Every domain profile now identifies:

- the persistent state it owns;
- configuration/runtime dependencies;
- scheduler/job/queue/outbox participation where applicable;
- normal healthy progression and implemented diagnostics;
- failure modes and the safe owning recovery path;
- replay/idempotency/reconciliation behavior;
- PostgreSQL versus external/private-storage/secret recovery dependencies;
- restore/migration/application-rollback boundaries;
- capacity/query/performance assumptions and regression-versus-capacity evidence boundaries;
- external-service degradation behavior;
- prohibited operator shortcuts and escalation/stop conditions; and
- operational evidence to retain.

## 8. Critical operational distinctions

P3 makes the following repository-wide distinctions explicit:

- scheduler health is separate from Horizon/queue health;
- a restart is not recovery acceptance without durable-state verification;
- pending deterministic work may be safely caught up through its owning bounded command;
- outbox publication is at-least-once and does not replay the originating business mutation;
- permanently failed webhook delivery has no current generic operator replay contract;
- database restore does not undo external side effects already delivered;
- Content production recovery includes private media plus database/config/key dependencies;
- Identity encrypted-state recovery depends on the correct `APP_KEY`;
- Recruitment retention must be reconciled after restoring older personal-data state; and
- destructive Platform recovery never bypasses legal holds, ownership, assurance or lifecycle deadlines.

## 9. Navigation and ownership

Navigation now exposes:

- P3 standard/matrix from product governance;
- all 14 operations profiles from the shared operations index and domain index;
- focused runbooks from their owning domain profiles; and
- shared deployment/config/observability/recovery authority from top-level `docs/operations/`.

Domain-specific living operations documentation remains under `docs/domains/<domain>/operations/`.

## 10. CI enforcement

`tests/Architecture/OperationsDocumentationTest.php` now verifies:

- exactly 14 canonical code domains and 14 matching operations profiles;
- profile metadata and required 12-section ordering;
- links to the owning domain and shared operations index;
- the exact frozen six-runbook P3 inventory;
- focused-runbook indexing, metadata and required 10-section ordering;
- retention/indexing of the three accepted Kingdoms operations guides;
- new domain runbooks are not misplaced under shared `docs/operations/`; and
- the shared operations index links the standard, matrix and all domain profiles.

Existing repository architecture tests still enforce filename/ownership rules and local Markdown-link integrity.

## 11. Deferred work is phase-owned, not a P3 gap

P3 deliberately does not inventory every public/internal interface/event/job contract at P4 depth, map every documentation assertion to immutable tests/evidence at P5 depth, or consolidate all system-level architecture/governance at P6 depth.

Those later phases may deepen discoverability/evidence but cannot be used to excuse missing current operational diagnosis, recovery, rollback, capacity, degradation or operator-safety semantics.

## 12. Validation gate

Before this report becomes Complete:

- protected Dependency Review must pass;
- protected CodeQL must pass;
- the main CI workflow must pass, including the new P3 architecture tests and repository-wide Markdown-link validation;
- immutable image, staging, backup/restore and image-scan gates must pass where included by CI;
- exact validated head/check identifiers must be recorded; and
- the DCP status ledger must mark P3 Complete and select P4 as current.

Until then, the correct `continue` decision remains **finish DCP-P3**.
