# Contributions security profile

[← Contributions domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary security boundary:** tenant-private contribution/reporting data with privileged management/export and explainable non-destructive history

## 1. Security purpose and scope

Contributions protects Alliance-private contribution records, evidence, reporting, exports, scheduled reports, and derived Event-attendance records from unauthorized disclosure or integrity loss. Security includes preserving explainability when values are calculated, corrected, reversed, or reconciled.

## 2. Assets and sensitive data

Assets include member-linked contribution records, evidence references, subjective assessments/data classes, calculation provenance, correction/reversal history, data-quality flags, reports, exports, report schedules/runs, and Events-derived reconciliation state.

Member self-service data is private to the owning Alliance and applicable member audience. Manager reporting/export can expose broader tenant data and is a privileged disclosure boundary.

## 3. Actors, authentication and authorization

Authenticated verified active members may view supported self-service progress/history and explicitly enabled leaderboards. Management, approval, correction, reversal, reconciliation, data-quality operations, exports, and report schedules require `contributions.manage` plus required recent Identity assurance.

Possession of a record/category/export identifier never bypasses active Alliance scope.

## 4. Tenant and privacy boundaries

All records, categories, reports, schedules, exports, and derived records are Alliance scoped. Cross-tenant aggregation is not part of the accepted capability.

Subjective/private evidence must not be represented as unexplained objective scoring or copied into generic logs/audit/outbox payloads. Public exposure is not implied by leaderboard or Integrations support.

## 5. Trust boundaries and data flows

Material flows are member/manager browser → tenant-scoped contribution surfaces, Events-owned attendance facts → deterministic reconciliation, Contributions schedules → Notifications due-time coordination, persisted records → authorized report/export generation, and approved bounded records → Integrations read-only representation.

Each receiving domain retains its own authorization and payload-minimization rules.

## 6. Threats, abuse cases and controls

Threats include cross-Alliance record/export access, unauthorized manager mutation, history tampering, opaque or misleading scoring, duplicate Event-derived records, private evidence leakage through exports/logs, and treating missing evidence as a numeric result.

Controls include explicit tenant queries, `contributions.manage`, immutable/link-preserving correction/reversal history, versioned calculation provenance, deterministic reconciliation identity, bounded authorized exports, explicit leaderboard eligibility, and data-quality states that do not mutate values silently.

## 7. Integrity, concurrency and idempotency

Corrections/reversals preserve prior records and links rather than overwriting history. Events-derived reconciliation is deterministic and idempotent and must reverse/restore derived state according to the source fact without editing Events persistence.

Scheduled report requests use deterministic occurrence identity so scheduler retries do not create duplicate logical work.

## 8. Secrets and credential handling

Contributions owns no authentication/API/webhook secret. Evidence or export payloads must not contain credentials, recovery material, signing secrets, or unrelated private fields from source domains.

Generated export files are sensitive tenant artifacts and follow tenant-safe path/storage/download controls defined by shared runtime contracts.

## 9. Destructive operations, retention and deletion

Normal correction/reversal is non-destructive. Report/export retention and account/Alliance lifecycle are coordinated with Platform while preserving the minimum historical provenance required for explainability and legitimate evidence obligations.

If retained records are anonymized during account deletion, member-identifying data must be minimized without fabricating or rewriting historical business meaning.

## 10. Auditability, observability and evidence

Privileged mutations, reconciliation, and export/report actions are attributable where required. Operators distinguish source-data gaps, evidence gaps, approval/correction state, reconciliation status, schedule/run state, and export completion.

Tests cover authorization, tenant isolation, calculation/version semantics, correction/reversal history, reconciliation idempotency, exports/reporting, and schedule behavior. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Authorized exports can legitimately concentrate tenant-private data; operational storage/retention/download controls remain important beyond controller authorization. Subjective categories can still be misused socially even when technically explainable, so the product intentionally avoids hidden punitive scoring semantics.

Contributions does not edit Events attendance, expose cross-tenant analytics, own machine credentials/webhooks, or produce unexplained automated quality scores.

## 12. Focused reviews and related documentation

No focused living Contributions security review is required at current complexity.

- [Contributions domain contract](../README.md)
- [Event attendance reconciliation](../event-reconciliation.md)
- [Events security profile](../../events/security/README.md)
- [Notifications security profile](../../notifications/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 5 threat model](../../../security/phase-5-threat-model.md)
