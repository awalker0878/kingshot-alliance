# Intelligence — Observations

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Observations`

Observations owns durable observed game facts and their provenance.

Observed Player/Alliance/Kingdom facts reference stable identifiers but do not become a second writable source of GameWorld identity or Alliance membership.

## Territory spatial observations

Territory Plan vs Observed Reality extends this existing owner with append-only spatial observation batches. It does not create a `TerritoryReality` context.

A spatial observation batch owns:

- Alliance and Kingdom scope;
- reviewer-confirmed `captured_at`;
- observation coverage kind and completeness;
- explicit visible bounds when required;
- immutable `KingdomMaps` dataset ID/checksum pin;
- Evidence/review provenance scalars;
- accepted-by Player and acceptance time;
- correction/invalidation history;
- closed observed object facts for HQ, Banner, Governor City and Bear Trap coordinates;
- reviewed Governor identity state: resolved Player, resolved plan-local identity, ambiguous or unresolved;
- extraction confidence/source metadata retained only as provenance on the accepted observation fact.

Observation coverage is explicit. `complete_hive`, `complete_visible_region`, `partial_region`, `single_object` and `unknown_coverage` are not interchangeable. `complete_hive` and `complete_visible_region` require explicit complete meaning; partial/unknown capture never proves global absence.

`missing` is not persisted by Observations. An absent object is only a comparison conclusion when an authorized read model can prove absence from the observation's completeness/coverage boundary. Otherwise it remains `not_observed`.

## Write boundary

Reviewed screenshot meaning enters Observations only through the owner Action `RecordSpatialObservationEvidence`. The Action:

1. reacquires current Alliance Intelligence authority;
2. verifies current Kingdom scope;
3. verifies the exact approved Evidence review through the Evidence-owned reference contract;
4. validates the exact immutable KingdomMap dataset/checksum;
5. validates coverage/completeness, coordinate bounds, closed object types and reviewed identity semantics;
6. appends the observation batch and child objects atomically;
7. enforces a stable destination idempotency receipt;
8. emits audit/outbox facts.

Evidence passes scalars/value objects only. No Evidence Eloquent model crosses this boundary.

## Correction and invalidation

Accepted spatial observations are historical facts and are never edited in place.

A correction appends a replacement observation with `corrects_observation_id`, invalidates the superseded observation with actor/time/reason, and retains both histories. Explicit invalidation likewise records actor/time/reason and does not delete historical provenance.

Current/latest projections exclude invalidated observations. Explicit historical detail may still expose an invalidated row to an authorized actor.

Deleting/redacting the originating Evidence does not delete an accepted spatial observation.

## Read boundary

`SpatialObservationQuery` authorizes before retrieving observation candidates and exposes only bounded Alliance/Kingdom history/latest/detail projections. It does not compare observations with Territory plans and does not persist freshness, missing, coverage-delta or drift state.

`ReadModels/TerritoryPlanning` is the composition boundary that combines an immutable published Territory revision with an authorized observation. `Operations/TerritoryPlanning` remains the owner of desired spatial intent and is never mutated by this observation capability.
