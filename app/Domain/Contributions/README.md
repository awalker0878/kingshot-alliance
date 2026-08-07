# Contributions domain

Phase 5 owns contribution categories, immutable contribution records, approval/correction/reversal workflows, event-participation reconciliation, data-quality flags, contribution reporting, leaderboards, exports, and scheduled report definitions.

## Reporting semantics

- `recorded_fact` identifies directly recorded operational facts.
- `calculated_metric` identifies values derived from a named, versioned calculation rule.
- `subjective_assessment` identifies explicitly subjective records and must never be presented as an objective fact.
- Corrections create a replacement record linked through `correction_of_record_id`; the original record is retained and reversed.
- Event participation is derived only from Phase 3 attendance records using the `event_attendance` calculation key.
- Leaderboards are category-level opt-in and always expose the calculation description/version used for the displayed total.
- Interactive exports create auditable report-run records with a report version, row count, and SHA-256 checksum.
- Scheduled reports are queued through the Notifications domain and transactional outbox using deterministic idempotency keys.

Phase 6 platform administration, billing, external webhooks, and generalized integration delivery are intentionally outside this domain.
