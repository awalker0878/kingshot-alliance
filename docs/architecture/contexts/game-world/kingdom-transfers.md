# GameWorld — KingdomTransfers

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/KingdomTransfers`

KingdomTransfers owns the domain state and rules for planning and executing Player/Kingdom transfer behavior.

## Boundary

A transfer may reference Player, Kingdom and Alliance identifiers, but the capability does not take ownership of Alliance membership or other context aggregates.

Cross-context effects use explicit owner Actions/events. The capability is not placed in `app/Workflows`; Kingdom transfer is a GameWorld business capability with its own state and invariants.