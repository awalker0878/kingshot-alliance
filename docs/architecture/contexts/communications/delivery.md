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

## Flow

1. A source context decides that a reminder is due and supplies render-ready `title`, `body`, and optional relative `action_url` metadata.
2. `NotificationDeliveryService` creates one idempotent row for every enabled channel. External channels are only included when the recipient has a configured endpoint.
3. In-app rows retain the existing outbox publication path. Source-context outbox listeners acknowledge only these in-app rows.
4. `ProcessNotificationDeliveries` independently claims due Discord and Telegram rows, sends them, and records provider acknowledgement or a bounded retry time.
5. The notification center exposes delivery state, read/dismiss actions, endpoint health, and per-channel preferences for the active Governor.

## Security rules

- Provider configuration uses Laravel's encrypted array cast and is hidden from serialization.
- Discord accepts only official HTTPS `discord.com` or `discordapp.com` webhook URLs with the expected webhook path.
- Telegram always targets the hard-coded `api.telegram.org` Bot API host; users configure only a validated bot token and chat ID.
- Discord mentions are disabled and provider payload lengths are bounded.
- Inbox, preference, and endpoint writes are scoped by authenticated account and active Governor.
- Credentials are never returned to the browser after save.

## Does not own

Delivery does not decide what an Event, King Perk, recruitment or transfer reminder means or when source-domain behavior is due. Those rules remain with the source capability.

Source contexts submit generic delivery intent through supported services and metadata. Communications does not import source-domain models to inspect the originating aggregate.

Business-specific classes such as `EventReminderDelivery`, `KingPerkReminderDelivery`, `MarkEventReminderSent` or `MarkKingPerkReminderSent` are outside the V3 boundary.
