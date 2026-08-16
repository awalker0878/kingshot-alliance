# Communications context

Status: Current  
Implementation: `app/Contexts/Communications`

Communications owns delivery coordination rather than the business fact that caused a message.

## Capabilities

- [Reminder and notification delivery](reminder-delivery.md)

## Boundary

Operations owns Event reminder policy/timing. Other source contexts own the facts that trigger their communications. Communications owns delivery attempts/preferences/channel/retry behavior and does not mutate the source aggregate.