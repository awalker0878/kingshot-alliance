# Communications — Delivery

Status: Current — Architecture V3

Implementation target: `app/Contexts/Communications/Delivery`

Delivery owns generic recipient notification routing, provider delivery and the user-facing Notification Center. Source contexts own notification meaning, eligibility and factual content.

## Canonical model

- `NotificationIntent` is the scalar/value-object boundary submitted by a source context.
- `NotificationMessage` is one logical recipient-visible notification and owns title/body/action, source subject, urgency, account/Governor scope, source idempotency identity and read/archive state.
- `NotificationDelivery` is one concrete route for one logical message and owns channel/endpoint selection, due time, digest cadence, provider status, attempts/retry and sanitized failure state.
- `NotificationEndpoint` is one named recipient/Governor destination for Discord, Telegram or Web Push.
- `NotificationPreference` stores account defaults and Governor overrides per notification type/channel.
- `NotificationRoutingPolicy` stores timezone, quiet hours, urgent bypass, temporary mute and digest cadence.
- `NotificationDigestDispatch` groups bounded due external routes without replacing the underlying logical messages.

One source notification produces exactly one `NotificationMessage` and zero or more `NotificationDelivery` routes. Multi-channel or multi-endpoint fan-out never duplicates inbox state.

## Supported channels

- In App
- Discord webhook
- Telegram Bot API
- Web Push
- Email

In App is native to Communications. Discord, Telegram and Web Push use encrypted named `NotificationEndpoint` records. Email consumes Accounts-owned verified email through `VerifiedNotificationEmailQuery`; Communications never copies or becomes authoritative for account email identity.

## Queue and routing flow

1. A source owner decides that a notification exists and submits `NotificationIntent` containing only recipient identity, optional Governor scope, notification type, source subject, render-ready title/body/action, generic urgency, availability time, source idempotency identity and bounded metadata.
2. `NotificationDeliveryService::queue` validates the boundary, including safe application-relative action URLs, and idempotently creates or reuses one logical `NotificationMessage`.
3. `NotificationRouteResolver` resolves current recipient preferences and routing policy into concrete routes. Routing is recalculated on every idempotent queue replay so a route that became newly eligible can be added without creating another logical message.
4. Concrete route identity is stable for the logical message plus channel plus endpoint/native destination. Replays therefore do not duplicate an existing route.
5. In-app routes are marked sent when materialized. External routes remain queued for immediate or digest processing.

Preference resolution is account default followed by Governor override when a Governor scope exists. Routing then applies endpoint availability, digest cadence, temporary mute and quiet hours. Urgent quiet-hours bypass is controlled by the recipient's routing policy; source contexts can assign the generic urgency but cannot choose the bypass behavior.

For account-scoped notification intents that carry eligible Governor IDs, external endpoint fan-out may resolve the owned eligible Governor destinations while preserving one account-scoped logical message.

## Immediate provider processing

`ProcessNotificationDeliveries` handles only due external routes with `digest_cadence=immediate`.

Before every provider attempt it reacquires and rechecks:

- the logical message;
- concrete endpoint existence and enabled state where applicable;
- current Governor ownership when a route is Governor-scoped;
- the current recipient preference/routing policy;
- current verified Accounts-owned email for email delivery;
- due time and attempt budget.

A route that is no longer authorized is cancelled rather than sent. A route whose current policy now requires digest/defer is moved back to the corresponding current schedule. Claiming is row-locked, stale pending work is recoverable, and provider attempts are bounded.

Provider acknowledgement marks only Communications delivery state. Failure may set a bounded retry time, including provider `Retry-After`, until the attempt budget is exhausted. Provider success or failure never changes source-domain truth.

## Digest processing

`BuildNotificationDigestDispatches` groups due non-immediate external routes by recipient, Governor scope, channel, concrete endpoint and digest window. Each dispatch is bounded to 20 member routes and has a stable group/window identity, so builder replay is idempotent.

`ProcessNotificationDigests` rechecks every member against the current endpoint, Governor ownership and recipient policy before delivery. Reauthorization uses the logical message's original `available_at` so a due hourly/daily digest is evaluated against its existing window rather than being perpetually advanced to the next window. A policy change to immediate delivery releases the member back to the immediate worker; a future defer removes it from the current dispatch. Retryable provider failure leaves member routes recoverable; a successful digest marks the included routes sent while the individual logical messages remain in the inbox.

The scheduler runs all three Communications delivery commands every minute with `onOneServer` and overlap protection:

- `notifications:deliver`
- `notifications:build-digests`
- `notifications:deliver-digests`

Source schedulers materialize only source-owned notification intent; Communications owns provider retry and recipient delivery timing.

## Endpoint lifecycle and health

Recipients may configure multiple named Discord, Telegram and Web Push endpoints. Every external stored-endpoint route records the concrete endpoint selected at queue time; provider processing does not substitute an arbitrary endpoint later.

Endpoint lifecycle supports save, test, pause, resume, reverify and delete. Endpoint configuration is encrypted and hidden from serialization. User-visible health is generic:

- `never_tested`
- `healthy`
- `degraded`
- `paused`

Successful test/provider delivery records healthy verification/success state. Provider failure records degraded/failure state and a bounded sanitized error, but transient rate limiting does not silently disable the endpoint. Pausing is an explicit recipient action and prevents delivery/test attempts until resumed.

Endpoint changes and test queueing produce audit evidence without exposing credentials.

## Provider security

- Discord accepts only official HTTPS `discord.com` or `discordapp.com` webhook URLs with the expected webhook path. Mentions are disabled and payloads are bounded.
- Telegram always targets the fixed `api.telegram.org` Bot API host; users configure only a validated bot token and chat ID.
- Web Push requires HTTPS endpoints on approved push-service hosts, rejects loopback/private/internal destinations, validates subscription key material, uses VAPID configuration and sends bounded encrypted payloads. Stale 404/410 subscriptions are non-retryable provider failures and affect only the concrete endpoint/route.
- Email resolves the current verified notification email from Accounts at route resolution and again before send. No email address is persisted as a Communications endpoint.

Production launch checks require the immediate/digest scheduler path. VAPID configuration is required when enabled Web Push endpoints exist, and a non-log/non-array mail transport with a valid sender is required when email notification preferences are enabled.

## Notification Center

`NotificationInboxQuery` reads logical `NotificationMessage` rows, not delivery routes. It provides bounded cursor pagination and filters for unread/all/archived state, notification type, account/Governor scope, date and delivery status.

Read/unread/archive/restore operations mutate message-owned inbox state. Shared preview/commit bulk handling is bounded to 50 logical message IDs and repeats current account/Governor ownership checks at commit. Delivery-route status is subordinate detail under one logical inbox item, so Discord/Telegram/Web Push/Email fan-out is visible without creating duplicate notifications.

The frontend exposes:

- logical inbox state and per-route delivery details;
- account defaults and Governor preference overrides;
- quiet hours, urgent behavior, temporary mute and digest cadence;
- named destinations and endpoint health/test/pause/delete controls;
- Web Push enable/disable/test controls and Accounts-owned email availability.

All supported notification type, channel, delivery-status and endpoint-health labels are localized, including `account.security`.

## Diagnostics and recovery

Recipient-visible diagnostics contain only safe routing/provider state needed to understand one logical notification. Platform Administration composes bounded delivery counts/queue age and recent failure cards using notification type, channel, status/attempt timing and an error fingerprint. It does not expose credentials, raw provider payloads, recipient IDs, message bodies or raw failure text.

Source-specific selective recovery first authorizes the source aggregate and then asks Communications to requeue bounded concrete failed route IDs. Communications repeats notification/subject/metadata, status and attempt-budget constraints while holding route locks.

## Boundary rules

Source contexts never inspect Communications persistence models. They submit `NotificationIntent` and receive scalar `NotificationQueueReceipt` data. Source callers do not select endpoints, email addresses, quiet-hour behavior, digest windows, provider retry timing or provider credentials.

Communications does not decide what an Alliance announcement, Event, King Perk, Gift Code, Intelligence signal, Officer Brief or account-security event means or when it becomes semantically due. Those rules remain with the owning source capability.

Communications does not import source-domain persistence models to inspect originating aggregates. Provider delivery state is operational evidence only; it is never authoritative evidence that the recipient completed a source-domain action.