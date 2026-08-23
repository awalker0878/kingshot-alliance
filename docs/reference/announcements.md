# Alliance announcements and broadcasts

Status: Current

Alliance announcements use the existing Content capability as their source of truth and the Communications Delivery capability for fanout. This avoids a second announcement store and keeps delivery retries outside Alliance-owned content state.

## Author flow

1. Open **Alliance Hall → Noticeboard → Manage**.
2. Create or edit an `Announcement`.
3. Enable **Notify active members when published** when the item should be broadcast.
4. Publish immediately or choose a future browser-local date and time.
5. For a published member-notifying announcement, optionally choose recurring weekdays, a wall-clock time, an IANA time zone and an end date.
6. Send a test to the current manager's enabled channels before relying on the rule.

Saving an existing item creates a new draft revision and deactivates any active recurring rule. Archiving also deactivates recurrence. Publishing the new revision creates a new one-off broadcast when member notification is enabled; recurrence must be deliberately saved again.

## Member reactions

Published Alliance Notices (`Announcement` Content) expose lightweight **Like** and **Dislike** controls to active Alliance members. One Governor may hold at most one reaction on a Notice and can switch or remove it. Repeating the same desired state is a no-op.

Reaction authority is intentionally independent from announcement authoring authority. The reaction write revalidates active Alliance membership but does not require `ContentManage`, publish, edit, archive or broadcast permission. A Dislike is not a report or moderation action.

Member Noticeboard reads expose only Like count, Dislike count and the current Governor's reaction. These values are informational and never affect publication order, visibility, prominence, delivery, moderation, recommendations, reputation or ranking. There is no net score, approval ratio, trending list or popularity sort.

Reaction mutations do not enqueue Communications notifications or broadcast deliveries.

## Recipient behavior

- Only claimed Governors with an active Alliance membership are recipients.
- Each recipient gets an in-app notification immediately.
- Discord and Telegram are added only when that Governor configured and enabled the channel.
- `alliance.announcement` preferences can disable any channel for the active Governor.
- Fanout is idempotent per broadcast run, Governor and channel.

## Delivery history and recovery

The management page keeps recent run history separate from the recurring rule. Each run shows its scheduled time, recipient total and current queued, sent, failed and read counts. This distinction prevents a successfully materialized schedule from being reported as externally delivered.

Retry is selective and bounded to 50 concrete failed delivery IDs. Content reauthorizes the manager and run scope; Communications then revalidates notification type, content subject, run metadata, failed state and remaining attempt budget under lock. Sent, unrelated and exhausted deliveries are not reset.

Cancelling a recurring rule requires the shared accessible confirmation dialog. Existing run and delivery evidence remains available after cancellation.

Matching webhook subscribers receive schedule updates/cancellations, queued-run summaries and privacy-safe external delivery outcomes. Outcome payloads include channel, status, attempt count and retryability, but never recipient identifiers, provider credentials or raw provider errors.

## Scheduling and operations

`content:publish-scheduled` publishes due content. `content:queue-announcement-broadcasts` then materializes due announcement deliveries. Both commands run every minute with overlap protection; external delivery continues through `notifications:deliver`.

One-off content keeps its completed-fanout marker. Recurring rules calculate the next occurrence from the rule's local wall-clock time and IANA time zone, materialize a durable run, and advance atomically. Re-running the worker cannot duplicate a run or delivery because both layers use deterministic idempotency keys.
