# Alliance — Access

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Access`

Access owns Alliance permission vocabulary, specialist role semantics and Alliance authorization interpretation.

## Authority inputs

The actor is the active Player. Access evaluates the concrete Alliance membership/rank/specialist-role facts needed by the requested Alliance capability.

## Invariants

- Alliance permissions are Player-scoped and Alliance-scoped;
- Platform Administrator is not a game-domain bypass;
- authorization services interpret permissions but do not acquire database locks;
- protected write Actions revalidate mutable authority inside the owner-controlled transaction;
- Operations and Intelligence define their own permission meanings even when they consume Alliance facts.