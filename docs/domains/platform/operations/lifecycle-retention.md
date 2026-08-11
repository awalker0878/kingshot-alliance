# Platform lifecycle and retention operations

[← Platform operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Platform  
**Capability:** Account/Alliance lifecycle, usage capture, legal hold, retention and anonymization  
**Code owner:** `app/Domain/Platform`

## 1. Scope, prerequisites and safety boundary

Use this runbook for overdue account-deletion requests, missing usage snapshots, retention backlog, lifecycle/restore problems, or legal-hold conflicts. Identify the target Alliance/User/request/hold IDs, current lifecycle/deadline state, release SHA and scheduler/database health before acting.

All destructive operations are fail-closed. Never bypass a hold, ownership/admin-assurance requirement, cooling-off/deadline or supported domain lifecycle rule.

## 2. Runtime and persistent state

Recurring Platform work includes:

- `platform:process-account-deletions --limit=100` hourly;
- `platform:capture-usage --limit=2000` hourly; and
- `platform:enforce-retention` daily at 03:45.

Durable state includes deletion requests/status/blockers, lifecycle/retention deadlines, legal holds, usage snapshots and retention-target metadata. Feature-domain rows remain semantically owned by their domains.

## 3. Healthy operating flow

1. Privileged lifecycle/destructive request is authorized and durable state is recorded.
2. Cooling-off/deadline/hold/ownership eligibility remains explicit.
3. Scheduler revisits due work and rechecks eligibility under current state.
4. Accepted deletion/anonymization/retention action runs through supported domain-aware logic.
5. Evidence/audit/outbox state records the accepted transition.
6. Usage snapshots append point-in-time operational state rather than rewriting history.

## 4. Signals and diagnostics

Check scheduler state, request status/blocker/retry timestamps, Alliance/account lifecycle, active legal holds, ownership/Platform-admin constraints, retention deadlines, last usage-snapshot time, eligible retention-target counts, command errors, audit/outbox evidence and database load.

Use `app:launch-check --json` for repository-controlled readiness context, but do not treat it as proof of legal/process approval.

## 5. Failure modes and triage

- Deletion remains pending: verify cooling-off/ownership/admin/hold eligibility and command health.
- Request blocked: resolve the named business/legal blocker; do not force state.
- Retention backlog: distinguish scheduler/database failure from records intentionally protected by hold/current lifecycle.
- Missing usage snapshot: restore scheduler/database and capture a new point-in-time snapshot; do not rewrite historical time.
- Restore denied after deadline: treat as expected lifecycle enforcement, not an operational bug unless code/docs disagree.

## 6. Recovery, replay and reconciliation

After dependency or legitimate blocker resolution, rerun the single bounded command. Each pass re-evaluates current eligibility, so stale UI assumptions do not authorize destruction.

For usage, a rerun creates a fresh snapshot rather than replacing the missed historical point. For retention/deletion, reconcile current domain state and evidence before catch-up; do not manually update status/deadlines to make work eligible.

## 7. Capacity and dependency degradation

Account-deletion and usage commands are bounded; retention work can touch multiple tables/domains. Catch-up may increase lock/storage/IO pressure, so keep defaults unless measured backlog/capacity supports larger safe work units.

Database degradation should pause destructive catch-up rather than encouraging manual deletion. Production legal/process ownership is external evidence.

## 8. Backup, migration and rollback

Create/verify the required pre-change backup before destructive deployment/recovery work according to the shared runbook. Logical deletion/anonymization may not be reversible by application rollback; a database restore has explicit data-loss and external-side-effect implications.

After restore, re-evaluate legal holds, lifecycle deadlines, deletion requests and feature-domain consistency before re-enabling scheduled destructive processing.

## 9. Stop conditions and prohibited operator actions

Stop if there is any uncertainty about legal hold, target ownership, Platform authority/MFA/password assurance, deletion deadline, cross-domain semantic ownership, or backup/recovery impact. Prohibited shortcuts include direct SQL deletes/anonymization, removing holds to unblock work, fabricating completed status, and deleting audit/outbox evidence.

## 10. Validation and evidence to retain

Verify only eligible records advanced, protected/blocked records remained unchanged, usage snapshots have correct new timestamps, retention backlog decreased only for eligible targets, and audit/outbox evidence is intact.

Retain release SHA, command/limit/timestamps, target/request/hold IDs, before/after status counts, backup/change/incident IDs, reviewer/approval identity in the approved system and validation results without copying unnecessary private data.
