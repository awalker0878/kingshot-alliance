# Communications context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Communications`

Communications owns generic recipient notification routing, logical inbox state and provider delivery coordination. It does not own the business fact that caused a notification or decide source-domain eligibility.

## Capability

```text
Communications/
└── Delivery/
```

**Delivery** owns:

- one logical notification message per source intent;
- account-default and Governor-scoped recipient preferences;
- recipient routing policy including quiet hours, generic urgency bypass, temporary mute and digest cadence;
- concrete In App, Discord, Telegram, Web Push and Email delivery routes;
- encrypted named Discord/Telegram/Web Push endpoints and their health lifecycle;
- provider attempts, acknowledgement, failure and bounded retry timing;
- bounded digest grouping/dispatch;
- read/unread/archive/restore inbox state;
- idempotency/deduplication and scalar queue receipts;
- privacy-safe recipient/platform delivery diagnostics.

Accounts remains authoritative for verified account email. Communications consumes that identity through a narrow scalar query and does not store email as a configurable endpoint.

## Vocabulary boundary

Communications uses generic delivery language such as:

```text
NotificationIntent
NotificationMessage
Delivery
Recipient
Preference
RoutingPolicy
Channel
Endpoint
Digest
Attempt
Failure
Retry
Idempotency
```

It does not encode source-domain concepts such as `EventReminder`, `KingPerkReminder`, Gift Code trust policy, Officer Brief factual derivation, recruitment reminders or Kingdom-transfer rules as Communications-owned business semantics.

Operations owns what an Event or King Perk reminder means and when it is semantically due. GameWorld/GiftCodes owns Gift Code campaign eligibility/revision identity. Accounts owns account-security meaning and verified email identity. Alliance and read-side publishers retain their own source facts. Communications owns only recipient routing and delivery after that source boundary is crossed.

## Integration boundary

Source contexts request generic delivery with `NotificationIntent`, containing scalar/value-object recipient/scope/type/subject/rendered-content/urgency/availability/idempotency data. They receive `NotificationQueueReceipt` scalar identities and do not inspect Communications persistence models.

Communications does not import Operations, Alliance or GameWorld Models to inspect source aggregates. A provider acknowledgement changes only Communications delivery state and never proves that a source-domain action was completed.

Immediate and digest workers reacquire current Communications/Accounts/Player-owned routing facts immediately before send. Source contexts do not select provider endpoints, quiet-hour behavior, digest windows, provider retry timing or credentials.

The detailed flow and security contract is documented in [Delivery](delivery.md). `Communications/Reminders` is not a V3 capability.