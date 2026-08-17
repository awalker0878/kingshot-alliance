# CA-P9 — Legacy Model Leakage Removal

Status: **PASS**

## Re-established checks

- No User-derived Alliance/Kingdom/rank authority helpers remain.
- No cross-context Eloquent relationships remain in business-context models.
- No non-boundary business context imports another context's Eloquent model.
- The only remaining foreign Eloquent model import is authenticated `Accounts\\Identity\\Models\\User` in HTTP/authentication adapters (18 sites), where the authentication boundary is allowed to resolve live account state.
- No public write Action returns an Eloquent model.
- No deleted Intelligence EventAnalysis/Roster query namespaces remain in active callers.
- Alliance request context exposes immutable scope/reference state rather than live Alliance/Player models.
- Platform API tenant resolution carries Alliance IDs/references and immutable tenant context, not Alliance Eloquent models.

## Repairs made in this phase

- Corrected stale Alliance creation/dashboard Player model assumptions to `PlayerReference`.
- Corrected Operations event-attention/visibility contracts to `PlayerReference`.
- Moved Platform administration, launch-readiness and event-type GET composition into `ReadModels`.
- Converted Platform event-type update route binding to scalar event type + owner query for configuration identity.
- Converted API credential tenant resolution to `AllianceReferenceQuery`.
- Converted write Action model returns to scalar IDs/immutable results.
- Added `PlayerSnapshotRecordResult` so snapshot writes expose immutable result data (`snapshotId`, `created`) rather than a live `PlayerSnapshot`.
- Converted `ResolveKingdom` to return `KingdomReference`.
- Moved the Eloquent-returning Alliance lifecycle mutation helper out of `Actions` into an owner-internal service.

## Verification

- `php tests/v3/Architecture/verify.php`: PASS
- Full PHP syntax scan across `app`, `routes`, `tests/v3`: PASS (681 files)
- Public Action Eloquent-return scan: 0 violations
- Non-boundary foreign Eloquent import scan: 0 violations
- Auth-boundary User model imports: 18 permitted sites

Known blockers: **NONE**

Safe to proceed: **YES**
