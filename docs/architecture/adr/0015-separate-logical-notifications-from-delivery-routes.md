# ADR-0015: Separate logical notifications from delivery routes

Status: Accepted

Date: 2026-09-04

## Context

The current Communications model stores inbox/read state and provider-delivery state on the same `NotificationDelivery` row. Channel fan-out therefore creates multiple rows for what the recipient experiences as one notification. That model becomes increasingly awkward once Communications supports multiple endpoints per channel, Web Push devices, quiet-hour deferral, digest delivery and richer diagnostics.

The application is a fresh deployment and has no compatibility or migration burden, so the canonical schema can be corrected directly rather than introducing transitional shims.

## Decision

Communications separates one logical recipient-visible `NotificationMessage` from one or more concrete `NotificationDelivery` routes.

`NotificationMessage` owns recipient scope, notification type, source subject reference, render-ready title/body/action URL, generic urgency, availability, source idempotency identity and inbox state.

`NotificationDelivery` owns the concrete route: message reference, channel, endpoint reference when external, provider status, due/queued/sent/failed/retry timestamps, attempt budget, route idempotency identity and safe routing reason.

External routes bind to a concrete `NotificationEndpoint` at queue time. Provider processing does not dynamically substitute a different endpoint.

Source contexts submit a scalar/value-object `NotificationIntent` and receive a scalar `NotificationQueueReceipt`. They never inspect Communications persistence models, credentials or routing state.

Recipient routing policy resolves account defaults, Governor overrides, urgency, quiet hours, temporary mute, digest mode and enabled endpoints. Source contexts may select only a generic urgency value; they do not decide recipient delivery mechanics.

## Consequences

- Multi-channel fan-out no longer duplicates inbox entries.
- Multiple endpoints per channel and multiple Web Push devices become natural route fan-out.
- Read/archive state remains stable regardless of provider retry state.
- Delivery diagnostics can be shown beneath one logical notification.
- Digesting can aggregate routes while leaving source notifications individually visible.
- Provider retry/failure remains Communications-owned and independent of source truth.
- Existing fresh-schema migrations and tests should be rewritten to the canonical model rather than preserving legacy rows or compatibility paths.

## Rejected alternatives

### Keep the combined delivery/inbox row

Rejected because endpoint fan-out and Web Push device fan-out would multiply recipient-visible records and make digest/routing behavior increasingly coupled to inbox state.

### Persist source-specific notification entities in Communications

Rejected because Event, Gift Code, Intelligence, Alliance and account-security meaning remains with source owners. Communications must stay generic.

### Resolve endpoints only when workers send

Rejected because route identity and idempotency would become unstable when endpoints are added, removed or reordered between queue and delivery.
