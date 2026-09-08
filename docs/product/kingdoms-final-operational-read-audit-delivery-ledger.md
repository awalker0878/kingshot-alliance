# Kingdoms final operational-read audit — delivery ledger

Status: Complete; delivery PR verified

- [x] Re-scan current `main` for remaining Kingdom historical lookup consumers.
- [x] Classify current/operational versus explicit historical surfaces.
- [x] Require active Kingdom identity on the Alliance dashboard and bot command feed.
- [x] Require active Kingdom identity for Kingdom role management.
- [x] Require active operating Kingdom identity for Intelligence tracking and ingestion management.
- [x] Require active Kingdom and active canonical tracked Alliance identity for diplomacy contact management.
- [x] Fail Transfer read authorization closed when the operating Kingdom is archived.
- [x] Preserve explicit Event, Governance, Player snapshot, and observation history semantics.
- [x] Preserve Transfer Evidence historical identity comparison behind active target authorization.
- [x] Add architecture and Transfer behavior regression coverage.
- [x] CI, Architecture V3 Verification, Intelligence Verification, Visual Regression, CodeQL, and Dependency Review green on the verified implementation head.
