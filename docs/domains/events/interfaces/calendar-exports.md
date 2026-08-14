# Event calendar exports

[← Events interfaces](README.md)

## Purpose

Defines the authenticated CSV and iCalendar projections of Events visible to the requesting User.

## CSV

`GET /events/export.csv` returns occurrence rows with:

- Event Type slug;
- title;
- scope;
- target label;
- UTC start/end;
- Event timezone; and
- occurrence status.

The query uses the same scope-aware visibility resolver as the web calendar.

## iCalendar

`GET /events/feed.ics` returns a bounded authenticated calendar projection. Occurrence ULIDs provide stable event UIDs. Start/end values are UTC and Event instructions plus target context are placed in the description.

No public bearer-token calendar URL is issued.

## Security

Both outputs require authentication and verification. Player visibility is derived from authoritative `players.user_id` ownership and delegated Alliance manager authority; Alliance and Kingdom visibility use their own authorization boundaries.
