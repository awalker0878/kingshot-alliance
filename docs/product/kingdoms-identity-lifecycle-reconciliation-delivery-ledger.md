# Kingdoms identity lifecycle and reconciliation — delivery ledger

Status: Complete; delivery PR verified

- [x] Define lifecycle/canonical identity ADR and context invariants.
- [x] Add explicit active Kingdom reference queries.
- [x] Add explicit active/canonical Kingdom Alliance reference queries.
- [x] Reject archived Kingdoms and Alliance identities at operational mutation boundaries.
- [x] Add Kingdom and Alliance archive/restore actions with shared audit events.
- [x] Add atomic Kingdom archive cascade and deliberately non-cascading restore.
- [x] Add temporal Alliance identity history.
- [x] Add source/provenance fields without copying owner-context evidence.
- [x] Add explicit canonical identity reconciliation and permanent alias preservation.
- [x] Add safe late stable-ID transfer and stable-ID conflict rejection.
- [x] Add advisory reconciliation candidate query with no fuzzy auto-merge.
- [x] Add Kingdoms integrity diagnostics.
- [x] Add dedicated Kingdoms Architecture V3 behavior tests.
- [x] Define fresh-deployment schema directly in the create migration; no compatibility layer.
- [x] Repository CI/Architecture V3 checks green on delivery PR.
