# Operations authorization

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Access`

Operations owns the permission vocabulary and authorization interpretation used for Event and operational behavior.

## Authority inputs

The actor is the active Player. Operations may consume current Alliance membership/rank/role facts or current Kingdom governance facts as inputs, but it interprets those facts using Operations permissions such as `events.player.*`, `events.alliance.*` and `events.kingdom.*`.

## Invariants

- authority is scoped to the concrete Player, Alliance or Kingdom involved;
- authority is never aggregated across Players owned by one User;
- Platform Administrator does not bypass Operations authorization;
- protected writes use Operations mutation authority and revalidate mutable scope/authority inside the transaction where required;
- Operations does not delegate its permission meaning to Alliance or Intelligence authorization services.
