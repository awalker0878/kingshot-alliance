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

Alliance announcements are authored and scheduled from the Noticeboard management page. See [Alliance announcements and broadcasts](announcements.md) for the author and recipient flow.

- In-app notifications are acknowledged as sent when they enter the inbox and are available without additional setup.
- A configured external channel receives Alliance announcements, Event reminders, King Perk reminders, Officer Briefs and Intelligence changes when that type/channel pair is enabled.
- Daily Officer Briefs become eligible after 09:00 in the account timezone and queue at most once per recipient/channel/local date. Upcoming Event, Post-Event Closeout and Intelligence change delivery is re-evaluated every 15 minutes and remains semantic-fingerprint idempotent.
- HTTP 429 and server failures receive bounded retries. Permanent provider failures remain visible in the Notification Center so the endpoint can be corrected.
- Removing an endpoint stops new fan-out to that provider; existing history remains visible until dismissed.
- Endpoint health shows the latest provider error and clears after a successful acknowledgement.

## Scheduled queue commands

- `notifications:queue-officer-briefs --group=daily --limit=1000` queues eligible recipient-local daily briefs.
- `notifications:queue-officer-briefs --group=event --limit=1000` queues changed Upcoming Event and Post-Event Closeout briefs.
- `notifications:queue-intelligence-changes --limit=1000` queues changed authorized Intelligence signals.

Each command returns bounded examined/eligible/fact/delivery counts plus a continuation cursor when the requested page is truncated. `--after=<membership-id>` resumes after that cursor. Scheduled invocations use `--cycle` to retain a short operational cursor in the shared cache, advance through every bounded page and wrap after the final page; the cursor contains no recipient or notification truth. Queue commands never report an external message as delivered; `notifications:deliver` owns provider attempts and retry.
