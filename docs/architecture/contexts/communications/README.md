# Communications context

Status: Current  
Implementation: `app/Contexts/Communications`

## Purpose

Communications owns delivery coordination rather than the business fact that caused a message.

## Owns

- reminder/notification delivery state;
- recipient delivery preferences where implemented;
- channel behavior;
- delivery retry and idempotency;
- delivery diagnostics appropriate for operational use.

## Does not own

- Event schedules or reminder policy;
- King Perks appointment timing;
- Alliance/Kingdom business decisions;
- source-context state.

Operations may decide that a reminder is due. Communications decides how that reminder is delivered and retried.