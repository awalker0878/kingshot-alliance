# Event planning: polls, rosters and battle plans

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Polls`, `Rosters`, `BattlePlans`

These are cohesive planning capabilities attached to Event execution rather than separate bounded contexts.

## Polls

Own Event polls, choices/voting state and poll lifecycle associated with operational planning.

## Rosters

Own Event roster planning, roster assignments and participation-oriented roster state. Roster planning references durable Player identity rather than using User as the participant.

## Battle plans

Own Event objectives and objective assignments. Assignments use explicit targets and Player identity where a Player is the target.

## Boundary

These capabilities may reference EventCore/Participation models directly inside the same Operations context because they share the operational consistency boundary. Analytical roster/history projections for reporting remain outside Operations.