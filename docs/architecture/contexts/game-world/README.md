# GameWorld context

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld`

GameWorld owns neutral game-world identity, Kingdom reference state, Kingdom governance and Kingdom transfer behavior.

## Capabilities

```text
GameWorld/
├── Players/
├── Kingdoms/
├── Governance/
└── KingdomTransfers/
```

- **Players** owns Player identity/claim, Player ownership reference and active Player selection.
- **Kingdoms** owns Kingdom identity and neutral Kingdom/Alliance placement/reference state.
- **Governance** owns Kingdom roles, assignments and GameWorld governance authorization semantics.
- **KingdomTransfers** owns Kingdom transfer planning and transfer-domain state.

## Identity boundary

User is the Accounts principal. Player is the game-domain principal.

GameWorld may retain scalar `user_id` to represent Player ownership, but `Player` does not expose a cross-context Eloquent relationship into Accounts `User`.

## Authority boundary

GameWorld interprets only GameWorld/Kingdom governance permissions. It does not interpret Alliance, Operations or Intelligence permission vocabularies.

## Workflow boundary

Active Player activation belongs to `Players`; it is not a Workflow. Kingdom transfer belongs to `KingdomTransfers`; it is not a Workflow simply because it references other context IDs.