# GameWorld

Neutral KingShot identity/world/governance foundation: Player, Kingdom, game-Alliance references, active Player context, current placement, versioned Kingdom-map truth, Kingdom governance, transfer primitives and Gift Code redemption state.

## Capability modules

- `Players` owns Player identity/claim, active Player context and Player-owned reference state.
- `Kingdoms` owns Kingdom identity and neutral Kingdom/Alliance placement/reference state.
- `KingdomMaps` owns immutable/versioned map datasets, coordinate/geometry facts, provenance and sourced game placement rules. It never owns Alliance planning preferences or mutable territory layouts.
- `Governance` owns Kingdom role/assignment state and Kingdom-owned authorization facts.
- `KingdomTransfers` owns transfer planning/readiness/completion state.
- `GiftCodes` owns the normalized catalogue and the per-Player, per-Kingdom redemption ledger.

Foundation models expose IDs/reference state only and do not navigate into higher-level contexts. Operations/TerritoryPlanning consumes KingdomMaps through explicit dataset/query contracts and scalar identifiers; it cannot mutate map truth.
