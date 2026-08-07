# Phase 5 Threat Model — Contributions and Reporting

## Scope

This review covers contribution categories, manual and self-reported records, approvals, corrections, reversals, event-derived participation, data-quality flags, member/leader reporting, leaderboards, CSV/spreadsheet exports, scheduled report definitions, and queued report requests.

## Protected assets

- alliance/member contribution and participation records;
- evidence and subjective-assessment text;
- correction/reversal provenance;
- calculation rules, versions, and inputs;
- attendance-derived participation links;
- data-quality findings;
- report schedules, report-run metadata, and export content;
- privileged actor/audit attribution.

## Trust boundaries

1. Authenticated member to active-alliance reporting context.
2. Authorized leader to privileged contribution-management routes.
3. Active alliance to category/member/record identifiers.
4. Events domain attendance truth to Contributions reconciliation.
5. Reporting query to downloadable export response.
6. Scheduler to Notifications/outbox report requests.

## Threats and controls

### Cross-alliance record access or mutation

**Threat:** A user supplies a category, membership, record, flag, or schedule target belonging to another alliance.

**Controls:**

- all Phase 5 routes require active-alliance context;
- management actions require `contributions.manage`;
- member self-report category lookup is scoped by active `alliance_id`;
- manual-record category/membership lookups are scoped by active alliance;
- route-bound mutable record/flag identifiers are re-resolved under active `alliance_id` and fail with 404;
- database relationships carry tenant identity/composite constraints;
- tenant-isolation tests cover foreign contribution record identifiers.

### Unauthorized manipulation of totals

**Threat:** A member approves, corrects, reverses, reconciles, or exports alliance-wide contribution data.

**Controls:**

- normal members may view their own history/progress and self-report only where the category permits it;
- privileged operations require `contributions.manage`;
- privileged HTTP mutations/exports require recent password confirmation;
- self-reported and manual records begin pending;
- approvals, corrections, reversals, and exports are audit attributed.

### Silent historical rewriting

**Threat:** A correction changes a past total without leaving evidence of the prior record or calculation.

**Controls:**

- correction never overwrites the original value;
- original records are reversed and retained;
- replacements link to `correction_of_record_id` and store correction reason;
- calculated records retain calculation key/version/inputs;
- exports include status and correction/reversal provenance.

### Calculated metric opacity or manipulation

**Threat:** Users cannot determine how a derived score was calculated, or a changed formula silently alters historical meaning.

**Controls:**

- calculated categories require a calculation key, version, and human-readable explanation;
- derived records copy calculation version/inputs at materialization time;
- leaderboards display the applicable calculation explanation/version;
- historical records are not recalculated merely because category configuration changes.

### Event-participation inconsistency

**Threat:** Contribution participation diverges from authoritative Phase 3 attendance.

**Controls:**

- event-derived records can only be created by reconciliation;
- `event_attendance` reads registrations recorded as `attended`;
- category/event-registration uniqueness prevents duplicate derived entries;
- reconciliation reverses/restores records when attendance changes;
- direct correction of event-derived records is rejected.

### Export disclosure

**Threat:** Alliance-wide reporting is downloaded by an unauthorized user or report content is confused with another tenant/version.

**Controls:**

- exports require active alliance, `contributions.manage`, recent password confirmation, and rate limiting;
- report rows are alliance-scoped;
- exported data carries alliance/report version identifiers;
- every export records row count, SHA-256 checksum, requester, and audit event.

**Residual risk:** Downloaded files leave application control; recipients must store/share them appropriately.

### Scheduled-report duplication or retry storm

**Threat:** scheduler retries create duplicate report requests or overwhelm downstream delivery.

**Controls:**

- due schedules are processed under row locks;
- deterministic idempotency keys are based on schedule/due time/report version;
- outbox rows use corresponding unique idempotency keys;
- scheduler uses `onOneServer()` and `withoutOverlapping()`;
- bounded queue limits apply per invocation.

### Data-quality misinformation

**Threat:** missing-data tooling changes contribution values or presents resolved flags as active defects.

**Controls:**

- quality refresh writes/resolves flags only; it does not mutate contribution records;
- flag state and resolution attribution are persisted;
- missing evidence/missing period record checks are explicit and explainable.

### Unsafe subjective assessments

**Threat:** subjective data is displayed as an objective calculated fact.

**Controls:**

- category data class explicitly separates `subjective_assessment` from facts/calculated metrics;
- reporting surfaces expose the data class;
- calculation metadata is required only for calculated metrics, avoiding false mathematical authority.

## Privacy and logging

Evidence, member emails, and subjective assessment text must not be used as metrics labels or routine log payloads. Audit records should prefer IDs and operational reasons required for accountability. Exports can contain alliance member operational data and should be treated accordingly.

## Security gate

Phase 5 acceptance requires static analysis, formatting, frontend checks, full tests, tenant isolation, migration rollback/reapply, CodeQL, dependency review, staging smoke, backup/restore, and image scanning to pass on the accepted final head with no unresolved critical/high security finding.
