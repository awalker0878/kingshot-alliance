# Alliance announcements and broadcasts

Status: Current

Alliance announcements use the existing Content capability as their source of truth and the Communications Delivery capability for concrete delivery. Bounded recipient preparation remains Content-owned. This avoids a second announcement store and keeps delivery retries outside Alliance-owned content state.

## Author flow

1. Open **Alliance Hall → Noticeboard → Manage**.
2. Create or edit an `Announcement`.
3. Enable **Notify active members when published** when the item should be broadcast.
4. Publish immediately or choose a future browser-local date and time.
5. For a published member-notifying announcement, optionally choose recurring weekdays, a wall-clock time, an IANA time zone and an end date.
6. Send a test to the current manager's enabled channels before relying on the rule.

Saving an existing item creates a new draft revision and deactivates any active recurring rule. Archiving also deactivates recurrence. Publishing the new revision creates a new one-off broadcast when member notification is enabled; recurrence must be deliberately saved again.

## Managing a larger Noticeboard

The manager catalogue shows twenty newest-created records per page and supports literal title search and a status filter. Categories and media have independent twenty-five-record pages. Overall Content/published/scheduled/awaiting-occurrence totals cover the current Alliance, not only the visible page. The knowledge-review list explicitly describes its current catalogue-page scope.

Continue with Next page; return to First page to include newly created records. Counts are current when read and may change between requests. Category/media pickers can search beyond the first page while retaining the currently selected value. Same-Alliance navigation retains unsaved editor and recurrence changes; changing the Alliance clears that state. A failed history request is shown explicitly and can be restarted rather than being mistaken for no history.

## Member reactions

Published Alliance Notices (`Announcement` Content) expose lightweight **Like** and **Dislike** controls to active Alliance members. One Governor may hold at most one reaction on a Notice and can switch or remove it. Repeating the same desired state is a no-op.

Reaction authority is intentionally independent from announcement authoring authority. The reaction write revalidates active Alliance membership but does not require `ContentManage`, publish, edit, archive or broadcast permission. A Dislike is not a report or moderation action.

Member Noticeboard reads expose only Like count, Dislike count and the current Governor's reaction. These values are informational and never affect publication order, visibility, prominence, delivery, moderation, recommendations, reputation or ranking. There is no net score, approval ratio, trending list or popularity sort.

Reaction mutations do not enqueue Communications notifications or broadcast deliveries.

## Recipient behavior

- Only claimed Governors with an active Alliance membership are recipients.
- Each visited eligible recipient receives an in-app notification when enabled. Bounded preparation can continue across scheduler invocations.
- Discord and Telegram are added only when that Governor configured and enabled the channel.
- `alliance.announcement` preferences can disable any channel for the active Governor.
- Fanout is idempotent per broadcast run, Governor and channel.

## Delivery history and recovery

The management page distinguishes a recorded occurrence from Pending preparation and completed recipient processing, with eligible, skipped, suppressed and replayed counters. Recurrence configuration remains separate. Queued preparation is not provider success. Open an item’s delivery history to page through all retained runs, five at a time. Revision history has independent ten-record pages; neither list is limited to the latest hundred Alliance runs. Authoritative preparation counters are not reconstructed from delivery samples.

Displayed read and channel-status totals include all retained matching messages/routes for the selected broadcast run, not the first 1,000 messages or 5,000 deliveries. A logical read is counted once even when it has multiple routes. These are retained-record outcomes, not an immutable all-time audience total or proof of human completion.

When more than 50 failures are below their attempt limit, the manager identifies how many are selected and the complete candidate count. Remaining candidates are not omitted from the total. Counts and candidate state can change while another worker runs; the retry Action remains authoritative.

Retry is selective and bounded to 50 concrete failed delivery IDs. Content reauthorizes the manager and run scope; Communications then revalidates notification type, actual content subject, matching Alliance/Content/run metadata, failed state and remaining attempt budget under lock. Sent, unrelated and exhausted deliveries are not reset.

Cancelling a recurring rule requires the shared accessible confirmation dialog. Existing run and delivery evidence remains available after cancellation. Unfinished recipients stop and obsolete queued external messages fail current source authorization; already-handed-off provider effects cannot be recalled.

Matching webhook subscribers receive schedule updates/cancellations, queued-run summaries and privacy-safe external delivery outcomes. Outcome payloads include channel, status, attempt count and retryability, but never recipient identifiers, provider credentials or raw provider errors.

## Scheduling and operations

`content:publish-scheduled` publishes due content. `content:queue-announcement-broadcasts --limit=25 --recipients=100` then records occurrences and resumes bounded recipient pages. Both commands run every minute with overlap protection; external delivery continues through `notifications:deliver`.

One-off content keeps an occurrence-created marker; only the run's queued timestamp means preparation completed. Identity includes publication revision. Recurring rules retain local wall-clock/DST semantics and advance atomically with occurrence creation. Settings saves/cancellation advance a generation, so A-to-B-to-A cannot revive old work. Each recipient commits with its cursor and existing Communications idempotency. [ADR-0049](../architecture/adr/0049-bounded-announcement-occurrences.md) defines finite traversal, current authorization and fairness.
