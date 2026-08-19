# Notifications

The Notification Center at `/notifications` provides an inbox and optional Discord or Telegram delivery for the active Governor.

## Configure a channel

1. Select the Governor whose reminders should be routed.
2. Open **Notification Center**.
3. Choose Discord or Telegram, add a recognizable label, and enter the provider credentials.
4. Use **Reminder routing** to enable or disable each reminder type per channel.

Saving a channel replaces the previous configuration for that Governor and channel. Credentials are encrypted at rest and are never displayed after saving.

### Discord

Create a webhook in the target Discord channel and paste its official webhook URL. Only HTTPS webhook URLs hosted by Discord are accepted. Messages disable automatic mentions.

### Telegram

Create a bot with BotFather, add it to the target chat, and provide the bot token and numeric chat ID. The application sends only through Telegram's official Bot API host.

## Delivery behavior

- In-app notifications are available without additional setup.
- A configured external channel receives Event and King Perk reminders when that channel is enabled.
- HTTP 429 and server failures receive bounded retries. Permanent provider failures remain visible in the Notification Center so the endpoint can be corrected.
- Removing an endpoint stops new fan-out to that provider; existing history remains visible until dismissed.
