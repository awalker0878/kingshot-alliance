# Event participation

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Participation`

Participation owns a Player's operational relationship to an Event occurrence.

## Current state families

- Event responses;
- registrations/waitlist behavior as implemented;
- attendance;
- frozen Player context required to preserve historical participation attribution.

## Invariants

Self-service participation resolves Player identity from the server-side active Player context rather than accepting arbitrary User/Player authority from the browser. Persistence is Player-keyed for occurrence participation.

Capacity, duplicate registration, waitlist/attendance transitions and similar concurrency-sensitive changes must be protected transactionally rather than by preflight UI checks alone.