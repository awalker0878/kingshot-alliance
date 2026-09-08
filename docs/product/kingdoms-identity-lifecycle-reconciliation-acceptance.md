# Kingdoms identity lifecycle and reconciliation — acceptance

Status: Current

| Area | Acceptance criterion |
| --- | --- |
| Kingdom resolution | Numeric normalization remains idempotent and archived Kingdoms are rejected for active resolution. |
| Alliance resolution | Stable IDs resolve idempotently; missing stable IDs create distinct neutral identities even for matching name/tag. |
| Lifecycle | Historical reads retain archived identities; active queries reject them; explicit restore is required. |
| Parent lifecycle | An archived Kingdom rejects Alliance resolution/mutation; Kingdom archive cascades to active child identities. |
| Restore | Restoring a Kingdom does not silently reactivate child Alliance identities. |
| Identity history | Create establishes a current history interval; changed name/tag/stable ID closes it and appends exactly one new current interval; no-op mutations append nothing. |
| Provenance | Identity changes can record source type/reference, observation time, confidence and reason without copying raw evidence. |
| Stable identity | Existing non-null stable game IDs cannot change in place and conflicting IDs fail closed. |
| Reconciliation | Only explicit same-Kingdom reconciliation can canonicalize identities; conflicting stable IDs are rejected. |
| Alias preservation | Duplicate row remains historically resolvable, archived, and points at canonical identity. |
| Late stable ID | A stable ID held by the duplicate transfers safely to an otherwise-unidentified canonical row. |
| Candidate detection | Name/tag similarity surfaces candidates only and never mutates identity. |
| Operational queries | Active/canonical query contracts exist independently of historical lookup contracts. |
| Diagnostics | Integrity query identifies invalid lifecycle state, current-history drift/multiplicity, canonical cycles and unresolved candidates. |
| Architecture | Kingdoms remains independent of Intelligence/Operations persistence and uses shared audit infrastructure. |
| Deployment | No compatibility shims, backfills or legacy dual-write paths exist. |
