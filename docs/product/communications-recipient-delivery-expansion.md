# Communications — Recipient Delivery & Notification Experience

Status: Selected extension

Implementation target: `app/Contexts/Communications/Delivery`

## Outcome

Extend the current complete Communications capability from channel enablement and provider retry into a recipient-owned notification experience with one logical inbox message, explicit routing policy, multiple concrete destinations, quiet hours, deferred/digest delivery, Web Push, email, endpoint health, bounded diagnostics and cursor-based inbox reads.

This is a fresh-deployment contract. No compatibility shims, backfills, dual writes or legacy schema preservation are required.

## Ownership boundary

Source contexts own the fact that a notification exists, its recipient eligibility, render-ready factual content, subject reference, source idempotency identity and generic urgency classification. Communications owns recipient preferences, route selection, quiet hours, temporary mute, digest/defer behavior, concrete endpoint selection, provider delivery, acknowledgement, retry/failure, logical inbox state, endpoint health and delivery diagnostics.

Communications must not import source-domain persistence models or infer source-domain importance. Provider success never proves that a recipient acted on the source fact.

## Canonical model

- `NotificationMessage` — one logical recipient-visible notification.
- `NotificationDelivery` — one concrete delivery route for one message.
- `NotificationEndpoint` — one named external destination/device.
- `NotificationPreference` — account or Governor preference for a notification type/channel.
- `NotificationRoutingPolicy` — recipient-owned routing settings including quiet hours, mute and digest cadence.
- `NotificationIntent` — scalar/value-object source contract supplied to Communications.
- `NotificationQueueReceipt` — scalar result returned to source callers.

One source notification produces exactly one `NotificationMessage` and zero or more `NotificationDelivery` routes. Channel fan-out must never duplicate inbox state.

## Supported channels

- In app
- Discord webhook
- Telegram Bot API
- Web Push
- Email

## Routing contract

Routing resolves account defaults, active-Governor overrides, endpoint availability, urgency, quiet hours, temporary mute and delivery mode into immutable concrete routes. Source contexts never select endpoints, provider credentials, quiet-hour decisions, digest windows or retry timing.

Preference inheritance is:

`account default -> Governor override -> temporary routing policy`.

Supported user presets may include All notifications, Important only, In-app only, Officer operations, Mute external and Custom, but presets must materialize explicit Communications-owned preferences rather than create hidden source-specific behavior.

## Quiet hours and urgency

Urgency is a closed generic vocabulary owned by Communications but selected by source callers: `low`, `normal`, `high`, `urgent`. Communications may defer external routes during recipient quiet hours. In-app messages remain available. Urgent bypass is recipient-controlled. Deferred routes must recheck current recipient routing authorization before provider send.

## Multiple destinations

Recipients may configure multiple named endpoints per external channel. Every queued external delivery references the concrete endpoint selected by routing. Provider processing never substitutes an arbitrary endpoint at send time.

Recipient endpoints remain recipient/Governor destinations; this extension does not create an Alliance-wide broadcast integration system.

## Web Push

Web Push subscriptions are concrete endpoints. Multiple devices are supported. Web Push uses the same routing, preference, quiet-hour, retry, idempotency and endpoint-health pipeline as Discord and Telegram. Subscription validation must defend against unsafe destinations and expired subscriptions.

## Email

Accounts remains authoritative for verified account email. Communications consumes only an explicit scalar query/contract for the verified notification address. Communications does not own or copy account email identity.

## Inbox

The Notification Center reads `NotificationMessage` with cursor pagination and filters for unread/all/archived, type, scope, date and delivery status. Read/unread/archive/restore actions remain recipient-scoped and bounded. Delivery details are subordinate to the logical message so multi-channel fan-out never appears as duplicate inbox messages.

## Digest delivery

Communications digests recipient-selected external delivery timing; it does not replace source-generated digests such as Officer Briefs. Constituent logical messages remain individually visible in app and retain their source handoffs. Digest dispatch and retry are idempotent and bounded.

## Endpoint lifecycle

External endpoints support save, test, pause, resume, reverify and delete. Health states are generic and may include never-tested, healthy, degraded and paused. Transient provider failures do not silently disable an endpoint. Credential material remains encrypted and is never returned after save.

## Diagnostics

Recipients can see safe routing and delivery state for their messages. Platform Administration may consume bounded privacy-safe aggregate diagnostics such as queue age, failure rate, retry exhaustion and degraded endpoint counts. Raw provider secrets and unnecessary message content are excluded.

## Security invariants

- no cross-account or cross-Governor reads/writes;
- provider credentials encrypted and hidden from serialization;
- relative/safe application action URLs only;
- bounded payload sizes, endpoint counts, route fan-out, bulk operations and digest size;
- endpoint testing rate-limited;
- Discord/Telegram/Web Push provider destinations validated;
- email identity remains Accounts-owned;
- provider failures never mutate source-domain truth.

## Delivery phases

1. Contract/ADR and current closeout fixes.
2. Logical message versus delivery-route model.
3. Routing policy and preference inheritance.
4. Quiet hours, urgency and mute.
5. Multiple named endpoints and endpoint health lifecycle.
6. Web Push.
7. Notification Center 2.0.
8. Generic digest delivery.
9. Email.
10. Delivery diagnostics and processor hardening.
11. Source integration normalization, security hardening, full acceptance evidence and documentation reconciliation.

Completion requires repository evidence for every acceptance criterion in `communications-recipient-delivery-acceptance.md` and every item in `communications-recipient-delivery-ledger.md`.