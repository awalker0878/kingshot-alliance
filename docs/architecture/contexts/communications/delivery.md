# Communications — Delivery

Status: Current — Architecture V3

Implementation target: `app/Contexts/Communications/Delivery`

Delivery owns generic notification delivery coordination.

## Owns

- generic notification delivery state;
- recipient delivery preferences;
- delivery channels;
- attempts and provider references;
- success/failure state;
- retry behavior;
- idempotency/deduplication.

## Does not own

Delivery does not decide what an Event, King Perk, recruitment or transfer reminder means or when source-domain behavior is due. Those rules remain with the source capability.

Source contexts submit generic delivery intent through supported contracts/events. Communications does not import source-domain Models to inspect the originating aggregate.

Business-specific classes such as `EventReminderDelivery`, `KingPerkReminderDelivery`, `MarkEventReminderSent` or `MarkKingPerkReminderSent` are outside the V3 boundary.