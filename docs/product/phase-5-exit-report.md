# Phase 5 Exit Report — Contributions and Reporting

**Status:** Accepted  
**Phase:** 5 — Contributions and reporting

## Outcome

The Phase 5 implementation delivers configurable contribution categories, member self-reporting, leader-entered records, approval/correction/reversal workflows, event-attendance reconciliation, explainable leaderboards, alliance operational reporting, data-quality flags, versioned CSV/spreadsheet exports, and scheduled report requests through the Notifications/outbox foundation.

## Implementation evidence

### Contributions and explainability

- Categories define unit, period, optional goal, evidence requirement, self-report permission, leaderboard opt-in, and explicit data class.
- Calculated categories require a calculation key, calculation version, and human-readable explanation.
- Contribution records persist the effective period and calculation provenance used when the record was created.
- Corrections create immutable replacement records; original records remain present and reversed with a reason.
- Reversals never silently delete historical records.

### Event participation

- Phase 5 derives participation from authoritative Phase 3 `attended` registration status.
- `event_attendance` reconciliation is idempotent and can create, reverse, or restore derived records as attendance truth changes.
- Event-derived records cannot be manually corrected; attendance must be corrected in the Events domain and reconciled again.

### Reporting and member experience

- Members can view their own approved progress, full contribution history, correction/reversal state, and calculation explanations.
- Members may self-report only in categories explicitly configured for self-report; these records remain pending until approved.
- Leaders can view contribution, attendance, recruitment, and membership trend summaries plus missing-data state.
- Leaderboards are category opt-in and expose calculation explanation/version.

### Exports and scheduled reports

- Authorized leaders can generate CSV and Excel-readable SpreadsheetML exports.
- Every interactive export records report version, requester, row count, SHA-256 checksum, and completion time and writes an audit event.
- Scheduled report definitions are timezone-aware and queue requests through Notifications/transactional outbox.
- Scheduled requests use deterministic idempotency keys and bounded, overlap-protected scheduler execution.

### Security and tenancy

- Phase 5 introduces `contributions.manage` for owners/leaders.
- Existing owner/leader roles receive the new permission through the Phase 5 migration; newly provisioned roles receive it from the canonical role template.
- Management, exports, corrections, approvals, reversals, reconciliation, data-quality operations, and schedules require active alliance context, `contributions.manage`, and recent password confirmation.
- Mutable record/flag identifiers are re-resolved under active alliance context and fail closed across tenants.

## Verification evidence

Phase-specific automated coverage includes:

- contribution approval/correction/reversal history;
- event-participation reconciliation and calculation versioning;
- export report version/checksum metadata;
- scheduled-report idempotency;
- member/manager authorization and cross-alliance denial;
- password-confirmation enforcement;
- migration rollback/reapply;
- contribution effective-period/timezone behavior;
- structural accessibility guards for Phase 5 Vue pages.

Final protected validation on the accepted implementation head passed:

- PHP formatting and static analysis;
- PostgreSQL migrations;
- full backend test suite: 163 tests and 1,395 assertions;
- frontend formatting, linting, type checking, and production build;
- tenant-isolation and migration rollback/reapply coverage;
- Dependency Review;
- CodeQL;
- immutable production-image build;
- ephemeral staging deployment;
- backup and restore demonstration;
- container image vulnerability scan.

## Documentation

- [Contributions and reporting](../domains/contributions-and-reporting.md)
- [Phase 5 operations](../operations/phase-5-operations.md)
- [Phase 5 migration and rollback](../operations/phase-5-migration-rollback.md)
- [Phase 5 threat model](../security/phase-5-threat-model.md)
- [Phase 5 accessibility review](phase-5-accessibility.md)

## Final gate

Phase 5 is **Accepted**. The implementation head passed the complete protected phase gate with no unresolved test, static-analysis, dependency, CodeQL, staging, recovery, or image-scan failure.

This acceptance update is documentation-only and must itself pass the repository's protected checks before merge.

Phase 6 platform scale/administration work is not included and must not begin as part of this phase.
