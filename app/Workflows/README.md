# Cross-context workflows

Only orchestration that genuinely spans bounded contexts belongs here.

Current workflows:
- `AccountOnboarding`
- `KingdomGovernance`

Rules:
- workflows own no business persistence or transactions;
- workflows import no context Eloquent models, repositories, migrations, or permission enums;
- workflows coordinate context-owned actions and queries through scalar IDs and immutable values;
- Player activation belongs to `GameWorld/Players`.
