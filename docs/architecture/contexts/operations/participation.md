# Operations — Participation

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Participation`

Participation owns Event registration, attendance/participation state and reminder business policy tied to participation/Event timing.

## Reminder boundary

Operations owns **whether and when** an Event reminder is due, including business rules, offsets and the Event/occurrence relationship that caused the reminder.

Communications/Delivery owns only generic delivery behavior: recipient preferences, channel, attempts, retry/failure and idempotency.

Participation does not navigate Communications persistence to decide delivery state; cross-context delivery is requested through an explicit generic contract/event.