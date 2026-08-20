# ADR-0005: Separate recurring broadcast intent and delivery

Status: Accepted

Date: 2026-08-20

## Context

A recurring announcement has three meanings that change independently: the author's future schedule, one due occurrence and each provider's delivery result. A single `broadcasted_at` marker cannot represent recurrence, partial channel failure, cancellation or selective recovery. Letting the Content context inspect Communications models would also make provider persistence part of Alliance behavior.

## Decision

The system separates the lifecycle into three records/contracts:

1. Alliance Content owns one optional `AnnouncementBroadcastSchedule` per content item. ISO weekdays, local `HH:MM`, IANA time zone, optional end and next/last occurrence make schedule intent explicit.
2. Alliance Content owns an immutable `AnnouncementBroadcastRun` for each materialized occurrence. A deterministic key makes worker replay safe and the run retains the recipient/delivery totals known at fanout time.
3. Communications owns `NotificationDelivery` status, provider attempts, errors, acknowledgements and retry budget. Its source-facing queue contract returns a scalar `QueuedDeliveryBatch`, not delivery models.

The cross-context management projection lives in `ReadModels/AnnouncementBroadcastManagement`. Content write controllers call only owner actions. Selective recovery first authorizes the Alliance run, then Communications locks and revalidates up to 50 concrete failed deliveries against notification type, subject and run metadata.

The occurrence calculator evaluates the rule in its named time zone before converting to UTC. This preserves the selected local wall-clock time through daylight-saving transitions. Saving a revision or archiving content cancels active recurrence.

## Consequences

- A queued run is never mislabeled as provider success.
- Rule cancellation does not delete delivery or audit history.
- Worker replay cannot duplicate an occurrence or recipient/channel delivery.
- Provider schema and failures remain inside Communications.
- Management reads can truthfully compose intent and outcome without giving a ReadModel write authority.
- Test delivery targets only the authenticated manager's active Player and enabled channels.

## Rejected alternatives

- Reusing only `content_items.broadcasted_at` was rejected because it represents one completion marker, not recurrence or partial outcomes.
- Copying provider status into Alliance tables was rejected because it creates dual writes and ambiguous recovery ownership.
- Having the Alliance controller query Communications directly was rejected because cross-context presentation belongs in an explicit ReadModel.
- Storing UTC alone was rejected because daylight-saving changes would move the author's intended local delivery time.
