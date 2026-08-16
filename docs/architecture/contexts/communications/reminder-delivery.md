# Reminder and notification delivery

Status: Current  
Context: Communications  
Implementation: `app/Contexts/Communications/Reminders`

## Owns

- delivery records/attempt state;
- recipient/channel preferences represented by the capability;
- retry/backoff/idempotency behavior;
- delivery-channel coordination and safe diagnostics.

## Does not own

- Event schedule/occurrence;
- King Perks appointment timing;
- the rule deciding a business reminder is due;
- Alliance/Kingdom business state.

Delivery is assumed retryable/at-least-once at infrastructure boundaries. Stable deduplication/idempotency prevents duplicate external effects. Diagnostic payloads must remain bounded and must not leak message secrets/private content.