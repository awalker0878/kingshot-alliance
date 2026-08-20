# Communications — Delivery

Status: Current — Architecture V3

Implementation target: `app/Contexts/Communications/Delivery`

Delivery owns generic notification coordination and the user-facing notification center.

## Owns

- generic delivery and inbox state;
- recipient and active-Governor preferences;
- encrypted external endpoint configuration;
- in-app, Discord webhook and Telegram Bot API channels;
- provider attempts, acknowledgement and failure state;
- bounded retry behavior with provider `Retry-After` support;
- idempotency and channel fan-out.
- scalar batch receipts for source contexts and owner-validated selective delivery recovery.

## Flow

1. A source context decides that a reminder or announcement is due and supplies render-ready `title`, `body`, and optional relative `action_url` metadata.
2. `NotificationDeliveryService` creates one idempotent row for every enabled channel. External channels are only included when the recipient has a configured endpoint.
3. In-app rows are acknowledged as sent when they enter the inbox; they do not wait for an external provider.
4. `ProcessNotificationDeliveries` independently claims due Discord and Telegram rows, sends them, and records provider acknowledgement or a bounded retry time.
5. The notification center exposes delivery state, read/dismiss actions, endpoint health, and per-channel preferences for the active Governor.

Inbox updates follow the shared previewed-bulk contract for selections of up to 50 concrete delivery IDs. Communications scopes the preview to the authenticated recipient plus the active Governor, reports already-read and already-dismissed rows as stable skips, and repeats the same ownership/state check before each write. Every result is explicit and failed IDs are returned for selective retry. The coordinator records aggregate audit evidence; it does not expose another recipient's delivery metadata in an unavailable result.

The scheduler runs `notifications:deliver` every minute with overlap protection. Source schedulers materialize only due intent; Communications remains responsible for provider retry timing.

Source contexts never inspect `NotificationDelivery` models. `queueEnabledChannelBatch` returns only delivery IDs and channel names. A source-specific recovery action first authorizes its aggregate, then asks Communications to requeue bounded concrete failed IDs with exact notification, subject and metadata constraints. Communications repeats status and attempt-budget checks while holding delivery locks.

## Security rules

- Provider configuration uses Laravel's encrypted array cast and is hidden from serialization.
- Discord accepts only official HTTPS `discord.com` or `discordapp.com` webhook URLs with the expected webhook path.
- Telegram always targets the hard-coded `api.telegram.org` Bot API host; users configure only a validated bot token and chat ID.
- Discord mentions are disabled and provider payload lengths are bounded.
- Inbox, preference, and endpoint writes are scoped by authenticated account and active Governor.
- Bulk inbox commits accept only bounded concrete ULIDs and never a browser-supplied recipient or Player identity.
- Credentials are never returned to the browser after save.

## Does not own

Delivery does not decide what an Alliance announcement, Event, King Perk, recruitment or transfer reminder means or when source-domain behavior is due. Those rules remain with the source capability.

Source contexts submit generic delivery intent through supported services and metadata. Communications does not import source-domain models to inspect the originating aggregate.

Business-specific classes such as `EventReminderDelivery`, `KingPerkReminderDelivery`, `MarkEventReminderSent` or `MarkKingPerkReminderSent` are outside the V3 boundary.
