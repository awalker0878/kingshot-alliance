# Operations — KingPerks

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/KingPerks`

KingPerks owns Kingdom of Power appointment and King Skill planning/scheduling against operational Event/phase timing.

## Core rules

- appointment occupancy is 30 minutes;
- Player cooldown is 60 minutes and is anchored after the appointment end;
- position cancellation blocks that position for 30 minutes;
- the scheduler validates occupancy/cooldown against the actual planned time rather than merely storing an arbitrary slot;
- source Event/phase timing remains Operations-owned;
- Communications owns generic delivery attempts, while reminder meaning/timing stays Operations-owned.

## Authority

KingPerks uses Operations authority derived from the active Player and concrete Kingdom scope. Platform Administrator is not a game-domain bypass.

## Lifecycle concepts

Planning supports plan creation/publication, appointment assignment/reassignment/confirmation/completion/no-show and skill planning/scheduling/activation. Durable events/outbox messages represent completed persisted transitions rather than becoming a second source of truth.