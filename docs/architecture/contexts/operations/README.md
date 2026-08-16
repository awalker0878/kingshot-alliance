# Operations context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations`

Operations owns execution-time game coordination centered on Events and operational scheduling.

## Capabilities

```text
Operations/
├── Access/
├── Events/
├── Participation/
├── Polls/
├── Rosters/
├── BattlePlans/
├── Rallies/
├── KingPerks/
└── Results/
```

- **Access** owns Operations permission vocabulary and authorization interpretation.
- **Events** owns Event identity, scheduling and occurrences.
- **Participation** owns registration/attendance and Operations reminder policy tied to participation/Event timing.
- **Polls** owns Event polls and voting.
- **Rosters** owns Event roster planning.
- **BattlePlans** owns objectives and assignments.
- **Rallies** owns rally coordination.
- **KingPerks** owns Kingdom of Power appointment/skill planning and scheduling.
- **Results** owns authoritative operational results and metrics.

## Boundary

Operations owns live operational state. Intelligence owns analytical/history projections. Communications owns generic delivery state, not Event or King Perk reminder meaning.

`EventCore` is not a V3 capability name; the capability is `Events`.

Reminder rules are part of the owning Operations behavior and do not create a separate Communications business capability.