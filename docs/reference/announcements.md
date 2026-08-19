# Alliance announcements and broadcasts

Status: Current

Alliance announcements use the existing Content capability as their source of truth and the Communications Delivery capability for fanout. This avoids a second announcement store and keeps delivery retries outside Alliance-owned content state.

## Author flow

1. Open **Alliance Hall → Noticeboard → Manage**.
2. Create or edit an `Announcement`.
3. Enable **Notify active members when published** when the item should be broadcast.
4. Publish immediately or choose a future browser-local date and time.

Saving an existing item creates a new draft revision. Publishing that revision creates a new broadcast when member notification is enabled.

## Recipient behavior

- Only claimed Governors with an active Alliance membership are recipients.
- Each recipient gets an in-app notification immediately.
- Discord and Telegram are added only when that Governor configured and enabled the channel.
- `alliance.announcement` preferences can disable any channel for the active Governor.
- Fanout is idempotent per announcement, Governor and channel.

## Scheduling and operations

`content:publish-scheduled` publishes due content. `content:queue-announcement-broadcasts` then materializes due announcement deliveries. Both commands run every minute with overlap protection; external delivery continues through `notifications:deliver`.

The broadcast marker is written only after the complete recipient snapshot is queued. Re-running the worker cannot duplicate existing deliveries because every delivery has a deterministic idempotency key.
