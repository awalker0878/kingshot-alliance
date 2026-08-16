# Communications context

Status: Current — Architecture V3

Implementation target: `app/Contexts/Communications`

Communications owns generic notification delivery coordination. It does not own the business fact that caused a notification.

## Capability

```text
Communications/
└── Delivery/
```

**Delivery** owns:

- notification delivery records;
- recipient delivery preferences;
- channel selection/behavior;
- delivery attempts;
- success/failure state;
- retry behavior;
- idempotency/deduplication.

## Vocabulary boundary

Communications uses generic delivery language such as:

```text
Notification
Delivery
Recipient
Preference
Channel
Attempt
Failure
Retry
Idempotency
```

It does not encode source-domain concepts such as `EventReminder`, `KingPerkReminder`, recruitment reminders or Kingdom-transfer reminders.

Operations owns what an Event or King Perk reminder means and when it is due. Other source contexts own the facts that cause their notifications.

## Integration boundary

Source contexts request generic delivery through an explicit contract/event containing the data Communications requires. Communications does not import Operations, Alliance or GameWorld Models to inspect source aggregates.

`Communications/Reminders` is not a V3 capability.