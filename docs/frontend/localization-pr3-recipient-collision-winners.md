# Recipient Delivery — differing-value collision review

Verified baseline `a5151e717020cfe00a32aeddcca6279f9c7042fe`, PR #183 [parity run 41516635437](https://github.com/awalker0878/kingshot-alliance/actions/runs/41516635437).

**41 differing-value collisions**, plus 21 identical-value overlaps and 101 newly introduced keys (163 overlay leaves total). This is a **source-derived, committed reviewer inventory**, not a translation review or authorization decision. Values below preserve the original loader's **last-overlay-wins** order for `communications-recipient-delivery-labels.ts` into `messages/account/en.ts`. The parity job also saves full `recipient-overlay-overlap.json` and compares this committed table against all generated changed-value collisions. Do not edit this list without the source-provenance check.

| Exact message key | Original account domain | Preserved runtime winner |
|---|---|---|
| `notifications.eyebrow` | Governor notifications | Recipient delivery |
| `notifications.subtitle` | Keep reminders in one inbox and deliver them to Discord or Telegram for the active Governor. | Read each notification once, then choose how and when Kingshot Alliance may deliver it to your account and active Governor. |
| `notifications.externalChannels` | External channels | Destinations |
| `notifications.needsAttention` | Needs attention | Delivery issues |
| `notifications.latestActivity` | Latest notification activity | Notification Center 2.0 |
| `notifications.empty` | No notifications yet. | No notifications match these filters. |
| `notifications.deliveryChannels` | Discord & Telegram | Named destinations |
| `notifications.deliverySecurity` | Credentials are encrypted at rest and never shown again. External delivery is tied to the active Governor. | External credentials are encrypted and never returned to the browser. Each destination is scoped to the active Governor. |
| `notifications.label` | Label | Destination name |
| `notifications.webhookUrl` | Webhook URL | Discord webhook URL |
| `notifications.botToken` | Bot token | Telegram bot token |
| `notifications.chatId` | Chat ID | Telegram chat ID |
| `notifications.saveChannel` | Save {channel} channel | Add {channel} destination |
| `notifications.selectGovernor` | Select a Governor before configuring external delivery. | Select a Governor to manage external destinations. |
| `notifications.reminderRouting` | Reminder delivery | Account defaults and Governor overrides |
| `notifications.statuses.pending` | Pending | Sending |
| `notifications.types.allianceAnnouncement` | Alliance Notices | Alliance announcement |
| `notifications.types.eventReminder` | Event reminders | Event reminder |
| `notifications.types.giftCodeExpiring` | Gift Code expiry reminders | Gift Code expiring |
| `notifications.types.giftCodeAvailable` | New verified Gift Codes | Gift Code available |
| `notifications.types.giftCodeTrustChanged` | Gift Code trust changes | Gift Code trust changed |
| `notifications.types.intelligenceChange` | Intelligence changes | Intelligence change |
| `notifications.types.kingPerkReminder` | Position Perk reminders | King Perk reminder |
| `notifications.types.officerBrief` | Officer briefs | Officer brief |
| `notifications.bulk.selected` | {count} notifications selected | {count} messages selected |
| `notifications.bulk.help` | Review the current inbox state before applying a change. | Preview up to 50 concrete logical messages before applying an inbox change. |
| `notifications.bulk.operation` | Inbox action | Bulk operation |
| `notifications.bulk.markRead` | Mark as read | Mark read |
| `notifications.bulk.selectVisible` | Select up to 50 | Select shown |
| `notifications.bulk.selectNotification` | Select notification: {title} | Select {title} |
| `notifications.bulk.preview` | Preview update | Preview |
| `notifications.bulk.previewing` | Checking notifications… | Previewing… |
| `notifications.bulk.previewTitle` | Inbox update preview | Bulk action preview |
| `notifications.bulk.previewSummary` | {ready} can be updated and {blocked} are unavailable or already complete. | {ready} ready; {blocked} blocked or already current. |
| `notifications.bulk.confirm` | Apply to eligible notifications | Apply changes |
| `notifications.bulk.confirmTitle` | Confirm inbox update | Apply inbox changes? |
| `notifications.bulk.confirmDescription` | {operation} {count} eligible notifications? | Apply {operation} to {count} reviewed messages. |
| `notifications.bulk.applying` | Updating notifications… | Applying… |
| `notifications.bulk.resultTitle` | Inbox update result | Latest bulk result |
| `notifications.bulk.resultSummary` | {succeeded} updated. {failed} need review. {skipped} were already complete. | {succeeded} succeeded; {failed} failed; {skipped} skipped. |
| `notifications.bulk.failedSelected` | Notifications that could not be updated remain selected so you can review them. | Failed items remain selected so they can be reviewed or retried. |
