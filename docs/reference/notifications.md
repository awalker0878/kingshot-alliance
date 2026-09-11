# Notifications

The Notification Center at `/notifications` shows one logical inbox item for a notification, with separate delivery details for its channels and destinations. Choose the active Governor before managing Governor-specific settings. Account defaults and Governor overrides are distinct scopes; changing the active Governor does not combine different Governors' authority.

## Channels and destinations

| Channel | Destination |
| --- | --- |
| In App | The account's logical inbox; no external credentials are required. |
| Discord | A named destination using an official HTTPS Discord webhook. Automatic mentions are disabled. |
| Telegram | A named destination using a bot token and chat ID through the official Bot API. |
| Web Push | A named browser subscription on an approved HTTPS push-service host; browser permission and supported deployment configuration are required. |
| Email | The current verified account email owned by Accounts, not a separately stored endpoint. Enable the corresponding email preference explicitly. |

Saving a destination **adds a named endpoint**; it does not replace another destination for the same Governor and channel. A Governor can have at most twenty stored destinations across the stored-endpoint channels. Use a recognizable label to distinguish them.

Use the selected endpoint's edit action to change its label or credentials. Blank credential fields retain the existing secret; stored credentials are encrypted and are not displayed back in the UI. Editing validates the resulting configuration, enables that endpoint and resets its verification state. Use explicit pause/resume to control a destination without deleting it, test/reverify to queue a bounded test, and delete to remove that destination. An endpoint already paused cannot be tested until resumed. Removing one endpoint does not remove other destinations or the logical inbox history.

## Preferences and delivery timing

Account defaults apply unless a Governor-specific override is present for the notification type and channel. Resetting an override restores inheritance; it does not disable the account default. Recipient routing policy controls timezone, quiet hours, urgent bypass, temporary mute and immediate/hourly/daily digest cadence. In-app visibility is separate from an external route's deferral.

The source capability decides notification meaning and source eligibility. Examples include Alliance announcements, Event and King Perk reminders, Gift Code availability/trust/reminders, Officer Briefs, Intelligence changes and account security. See [Alliance announcements and broadcasts](announcements.md) and the [Communications recipient contract](../product/communications-recipient-delivery-expansion.md). A stored route or historical notification is not a grant of access to a source page.

## Inbox and delivery status

Unread/read and archive/restore state belong to the logical message. Filters and cursor pagination operate on logical notifications, so multiple destinations do not create duplicate inbox items. Per-route details show the selected destination, status, schedule, attempts and safe failure information. Bulk inbox changes are bounded and repeat current ownership checks.

In-app routes are acknowledged when materialized. External routes become sent only after a recorded provider acknowledgement. HTTP 429 and supported transient failures may receive bounded retries; a permanent failure or exhausted budget has no next attempt. A sent provider route does not prove that a person read it or completed an Event, rally or other source action.

Current-attempt fencing prevents a delayed worker from overwriting a newer result. An exhausted expired claim becomes a terminal failure without another provider request. Its acknowledgement may be unknown: external delivery can already have happened even when no result was recorded. Do not treat that diagnostic as proof of non-delivery or reset attempt counters to force another send. The [background-processing guide](../operations/background-processing.md) owns operational recovery and scheduler commands.

## Endpoint health

Destinations show `never_tested`, `healthy`, `degraded` or `paused` health. Health describes observed transport state, not source-domain truth, future availability or a guarantee of recipient access. A current accepted outcome updates health atomically with its delivery result; pausing is not undone by a delayed acknowledgement. Transient failures do not silently disable a destination. Configuration changes require their own current verification; known in-flight credential-generation and source-authorization hardening gaps remain tracked in the [delivery ledger](../product/codebase-hardening-delivery-ledger.md).

The [delivery architecture](../architecture/contexts/communications/delivery.md) is authoritative for routing, transport boundaries and immediate/digest processing. This reference describes the current multi-endpoint model, not a legacy replacement-channel interface.

### Health after a settings change

Saving destination settings or pausing/resuming a destination resets verification. A response to an already-running request can still appear in delivery history, but it cannot mark the replacement settings healthy or degraded. Test the current settings to establish their health. The [endpoint generation contract](../architecture/adr/0047-endpoint-verification-generations.md) does not promise to recall provider requests already in flight.
