# Kingdoms downstream active-consumer audit — delivery ledger

Status: Implementation complete; CI verification pending

- [x] Preserve historical `find()` / `require()` semantics.
- [x] Add explicit active Kingdom lock contracts for transaction-time enforcement.
- [x] Reject Player identity writes into archived Kingdoms.
- [x] Reject Kingdom Governance mutations and administrator bootstrap for archived Kingdoms.
- [x] Reject Alliance writes when the parent Kingdom is archived.
- [x] Reject Event writes when the target or target-parent Kingdom is archived.
- [x] Preserve historical Event target resolution after Kingdom archive.
- [x] Reject Transfer writes when the operating Kingdom is archived.
- [x] Reject mutable Transfer Evidence when its current target Kingdom is archived.
- [x] Move current settings, Governance, roster, intelligence, public, API, bot, recruitment, and broadcast surfaces to active Kingdom semantics.
- [x] Add dedicated downstream active-vs-historical V3 regression coverage.
- [ ] Repository CI, Architecture V3, Intelligence Verification, Visual Regression, CodeQL, and Dependency Review green on the final delivery head.
