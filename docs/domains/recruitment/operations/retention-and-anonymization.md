# Recruitment retention and anonymization operations

[← Recruitment operations](README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Recruitment  
**Capability:** Scheduled candidate retention and anonymization  
**Code owner:** `app/Domain/Recruitment`

## 1. Scope, prerequisites and safety boundary

Use this runbook when eligible unsuccessful candidates remain past retention or when the daily anonymization command fails. Identify the affected Alliance/candidate IDs, retention eligibility timestamps, release SHA, scheduler state and database health before acting.

Do not anonymize active/ineligible candidates, bypass required policy/legal exceptions, or use direct SQL field nulling as a substitute for the supported lifecycle.

## 2. Runtime and persistent state

`recruitment:purge-expired --limit=250` runs daily at 03:15 with single-server/overlap protection. Candidate/application records, lifecycle/retention state and anonymization markers live in PostgreSQL. The command rechecks/locks candidate eligibility before mutation.

## 3. Healthy operating flow

1. Candidate reaches an unsuccessful/retention-eligible lifecycle state.
2. Retention deadline becomes due according to current policy.
3. Scheduler invokes the bounded purge command.
4. Candidate is rechecked under current state and lock.
5. Supported anonymization removes identifying detail while preserving allowed minimal/history evidence.
6. Already anonymized or no-longer-eligible records are skipped on later passes.

## 4. Signals and diagnostics

Check scheduler process/list, command errors, candidate status, retention due timestamp, anonymized state/timestamp, relevant legal/policy blockers and aggregate past-due eligible count. Use safe IDs and request/trace/audit context; do not inspect/export full answers unless necessary and authorized.

## 5. Failure modes and triage

- Scheduler absent: due eligible candidates accumulate.
- PostgreSQL/lock failure: command exits and eligible records remain.
- Candidate skipped: recheck current lifecycle/deadline/blocker state before assuming a bug.
- Partial-looking privacy state: stop and compare supported anonymization fields/invariants; do not manually finish with SQL.
- Restore reintroduced older personal data: perform retention reconciliation before reopening normal operations.

## 6. Recovery, replay and reconciliation

After scheduler/database recovery run:

`php artisan recruitment:purge-expired --limit=250`

Repeat only while a known eligible backlog remains and database load is safe. The current-state recheck makes normal catch-up safe for already-anonymized/ineligible rows.

Reconciliation should compare current candidate lifecycle + due time + anonymization state. Do not alter lifecycle solely to make a record purgeable.

## 7. Capacity and dependency degradation

Default purge limit is 250. Privacy backlog catch-up can create write/lock pressure; use defaults first and assess PostgreSQL capacity before raising any implemented bound. Mail or other downstream onboarding dependencies are unrelated to anonymization recovery.

## 8. Backup, migration and rollback

Recruitment privacy state is PostgreSQL-backed. A database restore can restore personal data that had later been anonymized, so the recovery plan must rerun retention reconciliation after restore and before claiming privacy controls are current.

Application rollback does not automatically reverse anonymization. Database restore or destructive schema rollback has explicit privacy/data-loss implications and requires approved recovery handling.

## 9. Stop conditions and prohibited operator actions

Stop if eligibility is unclear, a legal/policy hold applies, candidate lifecycle is inconsistent, or the proposed action would expose extra applicant data, anonymize a non-eligible candidate, edit fields directly, delete audit evidence or change status merely to force retention.

## 10. Validation and evidence to retain

Verify eligible past-due count decreases, only eligible candidates changed, anonymized records satisfy the supported minimal-history contract, ineligible/active candidates remain untouched and the scheduler returns to healthy cadence.

Retain release SHA, command timestamp/limit/counts, safe candidate IDs, eligibility/anonymization timestamps, blocker category, incident/change ID and validation result without retaining unnecessary applicant answers or notes.
