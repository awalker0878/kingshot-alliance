# CA-P5 Certification — Transactional Authority

Status: PASS

Completed:
- Protected Alliance/Intelligence/Operations/Platform write paths were scanned for authorization performed before the mutation transaction.
- Removed the stale pre-transaction `AllianceAuthorization::allows()` write gate from media upload. The expensive scan/storage path now receives an early transactional authorization gate, while the final persistence transaction independently reacquires locked current authority before writing.
- Alliance writes authorize `AllianceMutationContext` acquired by `AllianceWriteState` inside transactions.
- Intelligence writes authorize fresh `AllianceAuthorityFacts`/`PlayerReference` through `AllianceIntelligenceWriteState` inside transactions.
- Operations event writes use capability-owned write-state services and authorize the locked mutation context inside the transaction.
- Platform writes acquire current Platform administrator grant state through `PlatformWriteState` inside the transaction.
- Read/presentation `allows()` checks remain permissible but are not used as the final authorization decision for protected writes.
- Pre-transaction routing hints such as invitation IDs are reloaded and revalidated under lock before mutation.

Executable evidence:
- Automated action scan found no remaining protected write that calls `->allows()` before its mutation transaction.
- Authorization-service scan confirms write locking is owned by write-state/mutation services, not request snapshots.
- PHP syntax check passes for the corrected media-write path.

Blockers: none.
Safe to proceed: yes.
