# Operations — Access

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Access`

Access owns Operations permission vocabulary and authorization interpretation for Events and operational behavior.

## Invariants

- the actor is the active Player;
- authority is scoped to the concrete Player/Alliance/Kingdom involved;
- authority is never aggregated across a User's Players;
- Platform Administrator is not an Operations bypass;
- Access may consume current Alliance/GameWorld facts but interprets them using Operations permissions;
- authorization services do not acquire database locks;
- write Actions revalidate mutable authority inside the owner-controlled transaction.