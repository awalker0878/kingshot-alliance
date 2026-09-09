# ADR-0037: Recruitment private retention and audit metadata

Status: Accepted

## Context

Unsuccessful-candidate retention already removes names, contact details, answers, notes, communications and candidate associations. Re-entry fields added later remained on the terminal row, and the alternate re-entry page still served it. Audit metadata also copied private reasons/review dates and candidate labels, while the application event copied free-form source text into delivery state. The product requires reasons/review dates to be audited and private controls to obey candidate retention.

## Decision

Recruitment retention remains authoritative for its due unsuccessful candidates. In the existing Alliance/candidate transaction, it additionally clears the candidate Player link, source and all re-entry fields, returning control to Normal. Both candidate detail and re-entry detail exclude anonymized rows. Aggregate stage and milestone dates remain for recruitment measurement.

AuditRecorder exposes scoped subject-metadata redaction for an owning retention action. It matches Alliance, event, exact subject morph type and subject ID, updating payloads in SQL through the existing subject index. Recruitment requests redaction only for application-submitted, candidate-tagged and re-entry-control-changed metadata. These payloads become an explicit retention_redacted marker; audit IDs, actor attribution, event, subject reference and event time remain. Redaction and the candidate's terminal marker commit or roll back together.

Current reasons/review dates remain in the authorized audit until retention. The re-entry outbox event carries control codes and reason/date-change flags, without copying private values. Application submission delivery carries candidate ID and a source-presence flag; free-form source remains in the candidate and its retention-bound audit. No production consumer requires the removed delivery text. This is the fresh application contract, with no fallback event shape or historical backfill.

## Consequences

Recruitment retention removes private application/review payloads while preserving operational event evidence and separate Membership/Player handoff facts. It does not rewrite Membership invitations or identity history. Seven owner/HTTP cases exercise actual conversion/control/purge, payload boundaries, scoped audit redaction, both competing orders, late failures and retry. Current authorization for governance timeline access to retained private metadata is separately tracked under HARD-092.
