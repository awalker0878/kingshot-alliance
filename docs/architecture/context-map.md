# Context map

Status: Current

The context map defines ownership and permitted collaboration at the business level.

```text
Accounts ---------> GameWorld <--------- Alliance
   |                   |                    |
   |                   |                    |
   |                   v                    v
   |              Operations ----------> Intelligence
   |                   |                    |
   |                   v                    |
   +-------------> Communications <---------+
                       |
                       v
                    Platform

Cross-context commands spanning owners are coordinated through Workflows.
Cross-context read composition is performed through ReadModels.
Shared infrastructure sits below all business contexts and owns no game policy.
```

Arrows indicate collaboration, not unrestricted dependency. A consumer receives the smallest supported fact or contract it needs and must not reach through another context's persistence model merely because both contexts share one database.

## Relationship rules

- **Accounts → GameWorld:** an authenticated User may own/claim Players, but Accounts does not own Player game state.
- **GameWorld → Alliance/Operations/Intelligence:** provides neutral Player, Kingdom and placement/governance facts without interpreting downstream permission vocabularies.
- **Alliance → Operations/Intelligence:** exposes current Player-scoped Alliance membership/governance facts; downstream contexts decide what those facts authorize for their own capabilities.
- **Operations → Intelligence:** Operations owns live Event execution facts; Intelligence owns analytical/history projections and observations rather than mutating Operations state.
- **Operations → Communications:** Operations owns reminder policy and business timing; Communications owns delivery state.
- **Platform → Operations:** Platform may administer system-wide Event-type configuration through explicit orchestration, but Operations remains owner of operational Event semantics.
- **Platform → business contexts:** platform administration controls SaaS lifecycle/entitlements; Platform Administrator is not a game-domain authorization bypass.

## Dependency test

Before introducing a direct dependency, ask:

1. Who owns the business invariant?
2. Is the consumer reading a stable fact or reaching into another aggregate?
3. Is a workflow required because more than one owner must mutate?
4. Is the result only a composed read and therefore a ReadModel?
5. Would a small contract/event remove persistence coupling?

If ownership becomes ambiguous, stop and resolve the boundary before adding another dependency.