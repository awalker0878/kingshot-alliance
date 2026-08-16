# Notification delivery

Status: Current  
Context: Communications  
Implementation: `app/Contexts/Communications/Delivery`

## Owns

- generic notification delivery records and attempt state;
- recipient/channel preferences;
- channel selection state supplied by the source context;
- retry/backoff/idempotency behavior;
- delivery-channel coordination and bounded diagnostics.

## Boundary contract

Source contexts decide that a business notification is due and provide only scalar delivery inputs: notification type, recipient identifiers, channel, due time, idempotency key, optional subject identifiers and bounded metadata. Communications persists and advances delivery state without navigating back into the source aggregate.

Operations therefore owns Event reminder rules, Event audience selection, King Perk reminder timing and every other Operations-specific notification policy. Communications does not inspect Event, King Perk, Alliance or Kingdom aggregates to decide whether a notification should exist.

Delivery is retryable/at-least-once at infrastructure boundaries. Stable idempotency keys prevent duplicate external effects. Diagnostic payloads must remain bounded and must not leak message secrets or private content.
