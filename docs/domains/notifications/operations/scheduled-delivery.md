# Notifications scheduled delivery operations

[← Notifications operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Notifications  
**Capability:** Event reminders and scheduled Contribution-report coordination  
**Code owner:** `app/Domain/Notifications`

## 1. Scope, prerequisites and safety boundary

Use this runbook when Event reminders or scheduled Contribution reports are not being resolved/queued on time. Identify the source Event/Player participation or Contribution schedule, expected due time/version, release SHA, scheduler state and Platform outbox health before acting.

Do not change source business history or delete deterministic coordination rows merely to retrigger work.

## 2. Runtime and persistent state

Current recurring commands are:

- `events:queue-reminders --limit=100`; and
- `contributions:queue-reports --limit=50`.

They run every minute under scheduler single-server/overlap protection. Durable reminder/report identities are deterministic from their source/due/version semantics and hand accepted asynchronous intent to the Platform outbox.

## 3. Healthy operating flow

1. Events/Contributions persist authoritative eligible source state.
2. Scheduler resolves the expected logical reminder/report row.
3. Due work is queued through one deterministic outbox intent.
4. Platform publishes the outbox event and the owning downstream consumer advances its state.
5. Repeated scheduler passes find/reuse the existing logical record rather than creating duplicates.

## 4. Signals and diagnostics

Check:

- scheduler process and `php artisan schedule:list`;
- source occurrence/Player eligibility or Contribution schedule eligibility/due time;
- reminder/report status and deterministic identity fields;
- matching outbox publication/error state;
- command/application logs and request/trace/audit context for the source mutation; and
- backlog age/count before running catch-up.

## 5. Failure modes and triage

- No delivery row: source eligibility changed, sync command stalled, or database failure.
- Materialized but not queued: due-state recheck, queue command failure, or database/outbox error.
- Outbox row unpublished: switch to the Platform outbox runbook.
- Duplicate-looking business effect: compare deterministic logical identity before changing anything; repeated source transitions may legitimately be distinct.
- Downstream external delivery issue: diagnose the actual consumer/integration rather than replaying source state.

## 6. Recovery, replay and reconciliation

After dependency recovery run only the needed bounded command, using its normal default first. Re-run while a known backlog remains and durable state is progressing.

Reconciliation is source-driven: compare authoritative Event/Contribution state with expected coordination rows and outbox intent. Existing logical rows must be reused. Do not manually create a second reminder/report row or set sent/completed state.

## 7. Capacity and dependency degradation

Default limits are deliberately bounded. Larger catch-up can increase database lock pressure and outbox backlog. Assess PostgreSQL and downstream outbox/consumer capacity before changing limits.

Redis/Horizon health is not proof of scheduler health, and scheduler health is not proof that downstream outbox consumers are healthy.

## 8. Backup, migration and rollback

Restore Notifications state consistently with its source Events/Contributions and Platform outbox. After restore, reconcile source eligibility against durable coordination/outbox records before running catch-up; otherwise a restored older snapshot can cause misunderstood side-effect history.

Use the shared immutable-image rollback procedure. Database schema reversal requires explicit compatibility/data-loss review.

## 9. Stop conditions and prohibited operator actions

Stop when source state and coordination state cannot be reconciled deterministically, repeated outbox publication fails with the same defect, database load makes catch-up unsafe, or recovery would require deleting idempotency rows, changing source Event/Contribution history, fabricating sent/completed state or deleting audit/outbox evidence.

## 10. Validation and evidence to retain

Verify the expected source records now have exactly one logical coordination row/outbox intent, backlog age/count falls, unrelated future work remains scheduled correctly and no duplicate logical records were created.

Retain release SHA, command/limit/timestamp, source and coordination/outbox IDs, due/version identity, backlog counts before/after, error class/request/trace IDs and validation outcome.
