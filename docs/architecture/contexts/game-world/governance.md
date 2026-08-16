# GameWorld — Governance

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Governance`

Governance owns Kingdom role definitions/assignments, GameWorld Kingdom permission interpretation and the owner-controlled write path for governance changes.

## Invariants

- Kingdom authority is Player-scoped and concrete-Kingdom-scoped;
- Platform Administrator is not a game-domain bypass;
- sensitive writes revalidate mutable governance state inside the owner transaction;
- authorization services interpret permission vocabulary but do not acquire database locks;
- Governance does not interpret Operations or Intelligence permissions.

`Workflows/KingdomGovernance` may coordinate a process spanning GameWorld and another owner, but Governance remains owner of Kingdom governance state.