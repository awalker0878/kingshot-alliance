# Operations context

Status: Current  
Implementation: `app/Contexts/Operations`

## Purpose

Operations owns execution-time game coordination centered on Events and operational scheduling.

## Capabilities

- `EventCore` — Event identity, scope, schedule, recurrence, occurrences, phases, templates and command endpoints;
- `Participation` — registration, responses, attendance and frozen Player context;
- `Polls` — Event polls/voting;
- `Rosters` — Event roster planning and assignments;
- `BattlePlans` — objectives and objective assignments;
- `Results` — operational Event results and result metrics;
- `Rallies` — Rally planning/execution attached to Event occurrences;
- `KingPerks` — Kingdom of Power appointment/skill planning and scheduling;
- `Reminders` — Event reminder rules and scheduling policy;
- `Access` — Operations-owned event permission interpretation.

## Boundary

Operations owns live operational state. Historical/analytical projections belong to Intelligence or ReadModels. Reminder delivery state belongs to Communications. Platform may orchestrate Event-type administration but does not own Event execution semantics.

See [King Perks](king-perks.md) for its timing invariants.