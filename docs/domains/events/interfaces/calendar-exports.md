# Events calendar exports

[← Events interfaces](README.md)

**Document type:** Living focused interface contract  
**Status:** Current  
**Owning domain:** Events  
**Capability:** Authenticated Event CSV and iCalendar exports  
**Code owner:** `app/Domain/Events`

## 1. Contract scope and owner

Events owns two first-party authenticated schedule-export contracts for the active Alliance: a CSV download and an iCalendar response. Both are synchronous read projections of upcoming persisted Event occurrences.

These outputs are distinct from the Integrations `/api/v1/events` machine JSON representation and do not provide a public calendar-subscription bearer token.

## 2. Entry points and caller classes

Entry points in `routes/web.php` are:

- `GET /alliance/events/export.csv` → `EventCalendarController::export`;
- `GET /alliance/events/feed.ics` → `EventCalendarController::ical`.

Both use the active `AllianceContext` and `AllianceEventQuery`. The query horizon is `pastDays: 0`, `futureDays: 366`.

## 3. Authorization, tenancy and rate limits

Both routes live inside the authenticated, session-backed, verified, active-Alliance route group. The caller must therefore be an authenticated member with a valid Alliance context.

The export methods do not accept a caller-selected Alliance identifier. Tenant scope comes exclusively from `AllianceContext`.

There is no dedicated per-export throttle in the current routes beyond shared application controls. The URLs are not anonymous/public subscription endpoints.

## 4. Request and input format

Both requests are HTTP GETs with no body schema and no supported export-filter parameters. The current date horizon is fixed by the controller.

Event source state comes from the canonical Alliance Event query. Only occurrences with a valid associated Event are serialized; an unexpected missing relation is skipped during export rather than causing cross-tenant fallback.

## 5. Response and output format

### CSV

Response properties:

- MIME: `text/csv; charset=UTF-8`;
- disposition: attachment;
- filename: `<slugified-alliance-name>-events.csv`;
- cache control: `private, no-store`.

The exact ordered CSV header is:

```text
event
starts_at_utc
ends_at_utc
alliance_timezone
capacity
status
```

Rows contain Event title, occurrence start/end as UTC-capable ISO-8601 strings, Event timezone, nullable capacity, and occurrence status.

### iCalendar

Response properties:

- MIME: `text/calendar; charset=UTF-8`;
- disposition: inline;
- filename: `<slugified-alliance-name>-events.ics`;
- cache control: `private, no-store`.

Calendar-level fields are:

```text
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Kingshot Alliance//Events//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:<escaped Alliance name> Events
X-WR-TIMEZONE:<escaped Alliance timezone>
```

Each occurrence emits one `VEVENT` with:

- `UID:<occurrence-id>@kingshot-alliance`;
- `DTSTAMP` in UTC `Ymd\THis\Z` form;
- `DTSTART` in UTC;
- `DTEND` in UTC;
- escaped `SUMMARY` from Event title; and
- optional escaped `DESCRIPTION` from Event instructions when non-blank.

The calendar terminates with `END:VCALENDAR` and CRLF line endings. Backslash, semicolon, comma, and line breaks are escaped by the controller's iCalendar escaping rule.

## 6. State changes, events and asynchronous behavior

CSV and ICS generation are synchronous reads. They do not mutate Event state, create registrations, enqueue jobs, publish outbox messages, or create persistent export-run evidence.

Reminder materialization/queueing is a separate Notifications-owned scheduled workflow and does not affect export response generation except through the same underlying Event state.

## 7. Failure, idempotency and retry

Authentication/verification/Alliance-context failures fail before data disclosure. CSV buffer-allocation failure returns a server error rather than a partial trusted export.

GET generation is read-only and can be retried. Repeated responses may differ in `DTSTAMP` and as underlying Event occurrences change; callers must not use byte identity as an Event-version identifier.

## 8. Versioning and compatibility

There is no explicit numeric CSV/ICS schema version. The documented output shape is therefore the current compatibility contract.

Breaking changes include removing/renaming/reordering CSV fields, changing UTC/timezone semantics, changing the ICS `PRODID`, changing occurrence UID construction, or making the authenticated URL a different access model. Such changes require documentation/tests and compatibility review.

The Integrations API remains independently versioned at `/api/v1`; its Event JSON representation is not an alias for either file format.

## 9. Security, privacy and operational constraints

Both outputs are Alliance-private authenticated responses and use `private, no-store`. They intentionally omit registration membership identities, attendance details, waitlist membership, manager-only coordination state, private Rally assignments, and Contribution records.

The current contract does **not** issue a long-lived public calendar token. Sharing an authenticated response file outside the application becomes the recipient's disclosure responsibility; the repository does not convert the route into anonymous access.

Operational diagnosis of schedule/occurrence state is documented in [Events operations](../operations/README.md).

## 10. Tests, non-capabilities and related documentation

Tests should protect tenant/authentication boundaries, exact CSV fields, Event horizon, iCalendar metadata/UID/UTC/escaping semantics, private cache behavior, and the absence of public-token access.

This contract does **not** provide:

- anonymous/public calendar subscription URLs;
- Event import;
- registration/attendance exports;
- Rally-assignment exports; or
- the external `/api/v1/events` JSON contract.

Related documentation:

- [Events interface profile](README.md)
- [Events domain](../README.md)
- [Event registration and attendance](../registration-and-attendance.md)
- [Events operations](../operations/README.md)
- [Integrations API](../../integrations/api.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
