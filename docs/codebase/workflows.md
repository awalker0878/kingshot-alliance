# Cross-context workflows

Status: Current

`app/Workflows` exists for user intents that require coordinated behavior across more than one bounded context.

Current workflow packages include:

- `KingdomGovernance`;
- `KingdomTransfer`;
- `PlayerContext`;
- `Registration`.

## Rules

A workflow:

- may invoke supported application contracts from participating contexts;
- may sequence owner operations and coordinate failure handling;
- may own workflow-specific authorization vocabulary when that vocabulary belongs to the workflow itself;
- does not become persistence owner of Player, Alliance, Event, Intelligence or account aggregates;
- must not import legacy `App\Domain\*` classes;
- must not become a compatibility façade masking incorrect ownership.

When only a composed read is needed, use a ReadModel instead of a workflow.