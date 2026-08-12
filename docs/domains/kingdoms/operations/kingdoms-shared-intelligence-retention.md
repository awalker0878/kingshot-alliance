# Kingdoms shared-intelligence retention and capacity operations

[← Kingdoms operations profile](README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living capability operations runbook  
**Status:** Current  
**Owning domain:** Kingdoms  
**Capability:** KINGDOMS-005 opt-in shared intelligence retention and bounded read capacity  
**Code owner:** `app/Domain/Kingdoms`

## 1. Scope, prerequisites and safety boundary

This runbook covers the K5 bounded retention command/schedule and the operational interpretation of the accepted current/history capacity boundaries.

The supported destructive operation is limited to old K5 operational consent/grant rows. It must never be used to delete or rewrite source-owned `TrackedKingdomAlliance` or `KingdomAllianceObservation` history, active shares/grants, Audit events, outbox messages, tenant identities or authorization state.

Prerequisites for an operator-initiated run:

- normal application configuration validation is green;
- PostgreSQL is reachable and healthy;
- migrations are current;
- the operator is executing the repository-supported Artisan command in the intended environment; and
- any production retention-window change has been separately reviewed before execution.

Immediate invitation-hash erasure on accept/decline/revoke is part of normal runtime and is not deferred to this runbook.

## 2. Runtime and persistent state

K5 operational sharing state lives in:

- `kingdom_intelligence_shares`; and
- `kingdom_intelligence_share_targets`.

Canonical source intelligence remains in the existing Kingdoms tracking/observation tables. Durable business evidence remains in `audit_events` and `outbox_messages`.

Default retention configuration in `config/kingdoms.php`:

- expired pending invitation rows: 30 days after invitation expiry;
- declined/revoked agreement rows: 180 days after terminal timestamp; and
- removed target rows: 90 days after removal.

The action uses one total per-run work budget. The default/scheduled budget is 500 and supported command/runtime bounds clamp it to 1–2000.

## 3. Healthy operating flow

The scheduled command is:

`kingdoms:enforce-sharing-retention --limit=500`

It runs daily at 04:30 using `onOneServer()` and `withoutOverlapping(60)`.

One healthy run processes eligible rows in priority order:

1. expired pending invitations;
2. old terminal agreements; then
3. old removed grants.

The command prints JSON with:

- `expiredInvitationsPurged`;
- `terminalSharesPurged`;
- `removedTargetsPurged`; and
- `processed`.

A zero-result run is healthy when no rows are eligible. Re-running after an already completed bounded cleanup is expected to be idempotent.

## 4. Signals and diagnostics

Retain safe operational evidence for an intentional/manual run:

- application/release SHA or immutable image identity;
- execution timestamp and environment;
- requested `--limit`;
- returned per-class counts and `processed` total;
- relevant change/incident identifier when run outside normal schedule; and
- post-run application/authorization validation result.

Use database state/counts and normal Audit/outbox evidence to diagnose whether rows are eligible. Do not capture invitation plaintext/hash values, history cursors, source observation payload bodies, manager notes, roster/transfer/diplomacy/contact data or K4 source/raw-response material as operational evidence.

There is no dedicated K5 health dashboard/alert in the repository. Do not claim one exists solely because the command returns counts.

## 5. Failure modes and triage

### Command fails before processing

Check application configuration, database connectivity, current migrations and application logs. Do not bypass the action with direct SQL deletion.

### `processed` is lower than expected

The action intentionally rechecks state/cutoff at deletion time. A candidate that became active/re-granted/non-eligible is preserved. Also verify the configured retention windows and the one-total-budget limit before assuming a defect.

### Eligible backlog exceeds one run

This is expected when eligible rows exceed the requested limit. Run another bounded invocation only after the prior invocation completes and database/application health remains acceptable.

### Active share/grant appears missing after maintenance

Stop further manual retention runs and treat this as an incident. The supported predicates must never delete active rows. Preserve release/command evidence and restore through approved recovery procedures if required; do not fabricate/reactivate state by direct database edit.

### Canonical source observation appears missing

Stop. K5 retention never intentionally deletes canonical observations. Treat this as a separate data-integrity/recovery incident and use the shared backup/restore procedures.

## 6. Recovery, replay and reconciliation

The retention command is safe to retry because eligibility is state/timestamp based and already-removed rows no longer match.

A database restore can reintroduce old operational K5 rows that existed at backup time. After completing normal restore validation:

1. verify live sharing authorization still reflects restored agreement/grant/context state;
2. verify canonical source observations and Audit/outbox evidence are present as expected;
3. run retention with a conservative bounded limit if old operational rows are now eligible; and
4. repeat only as needed while database/application health remains normal.

Do not replay or reconstruct deleted invitation credentials. A terminal collaboration requires the supported new-invitation flow if managers intend to collaborate again.

Do not use direct SQL to retarget source/recipient Alliances, change captured Kingdoms, reactivate terminal agreements/grants or manufacture retention timestamps.

## 7. Capacity and dependency degradation

Accepted repository regression/capacity evidence covers:

- 300 active explicit grants with current projection still capped at 250 rows and no more than two SELECTs;
- bounded current response fixture at or below 160,000 encoded bytes;
- 1,000 source observations for one target with history still capped to five 50-row pages / 250 accepted observations;
- no more than two SELECTs per history page, exactly 10 across the five-page fixture; and
- bounded history page fixture at or below 50,000 encoded bytes.

These are regression gates, not production throughput, latency or concurrent-user SLOs.

If database pressure is observed during manual catch-up, lower the retention `--limit`; do not exceed the implemented 2000 cap or disable state/cutoff predicates. Current/history reads have no new cache/external provider dependency in K5-P5, so degradation is primarily PostgreSQL/application capacity governed by shared runtime operations.

## 8. Backup, migration and rollback

P5 adds no schema migration. K5 state remains covered by the shared PostgreSQL backup/restore procedure.

The earlier P4 nullable invitation-hash migration remains part of the schema and must stay applied in normal forward operation. Application rollback does not imply database reversal.

Use [backup/restore](../../../operations/runbooks/backup-restore.md) and [rollback](../../../operations/runbooks/rollback.md) for repository-supported mechanics. After restore/rollback, verify:

- migrations are in the expected state;
- active agreements/grants still authorize only under valid live context;
- terminal/expired/removed rows do not authorize access;
- canonical source observations remain source-owned; and
- recipient canonical observation count remains unaffected by reads.

Do not reverse the P4 nullable-hash migration merely to recover from a retention issue.

## 9. Stop conditions and prohibited operator actions

Stop and escalate if the proposed action requires any of the following:

- deleting active shares or active target grants;
- deleting/rebuilding canonical source observations as part of K5 retention;
- deleting Audit/outbox evidence to make counts line up;
- recovering or logging invitation plaintext/hash material;
- changing share/target state, tenant IDs or captured Kingdom by direct SQL;
- bypassing the total work budget or the 2000-row clamp;
- disabling the delete-time state/cutoff recheck;
- copying source observations into recipient-owned history;
- using a public API/webhook/export as a retention workaround; or
- weakening the 50-row page, 250-observation traversal or opaque cursor rules for capacity reasons.

A larger backlog does not justify widening the sharing/authorization boundary.

## 10. Validation and evidence to retain

Accepted P5 runtime candidate: `b47f639a275652590304fccef051f78997a0153c`.

Protected evidence:

- Dependency Review `31570931190` — success;
- CodeQL `31570931290` — success;
- CI `31570931267` — success;
- Pint 559 files;
- PHPStan/Larastan 394/394, zero errors;
- 451 tests / 10,230 assertions;
- frontend lint/format/type/build — success;
- immutable image build — success;
- staging deployment — success;
- backup/restore demonstration — success;
- image scan — success; and
- cleanup — success.

Focused evidence is in `KingdomSharedIntelligenceRetentionTest` and `KingdomSharedIntelligenceCapacityTest`.

See [Slice E validation](../product/kingdoms-shared-intelligence-slice-e-validation.md), [Slice E security review](../security/kingdoms-shared-intelligence-retention-security-review.md), [living shared-intelligence contract](../shared-intelligence.md), and the [Kingdoms operations profile](README.md).
