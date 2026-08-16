# Event core

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/EventCore`

## Owns

- Event identity and target scope;
- scheduling and recurrence;
- Event occurrences;
- phases and templates;
- enabled operational capabilities;
- Event command entry points and core relationships to capability state.

## Scope

Operations supports Player, Alliance and Kingdom Event scopes. Target identity is durable historical context: later membership/placement changes do not retarget a historical Event.

## Authority

Operations owns Event permission semantics. Permission families distinguish Player, Alliance and Kingdom scope and create/view/manage behavior. GameWorld/Alliance provide current actor/scope facts; Operations decides what those facts authorize for an Event.

## Boundary

Event history/trends are analytical projections owned by Intelligence/ReadModels. Platform Event-type administration configures catalogue/platform concerns through explicit orchestration rather than owning live Event state.