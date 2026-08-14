# Events interfaces

[← Events domain](../README.md)

## 1. Purpose

Defines the current first-party and file-output interfaces owned by Events.

## 2. Browser routes

- `GET /events` — authorized scoped Event calendar/agenda.
- `GET /events/create` — permission-aware Event creation using database Event Type defaults.
- `GET /events/{occurrence}` — one authorized occurrence detail view.
- `GET /events/{event}/manage` — Event schedule/instructions management.
- `POST /events` — create a scoped Event.
- `PATCH /events/{event}` — update mutable schedule/instruction fields.
- `DELETE /events/{event}` — cancel an Event.
- `POST /event-templates` — save a reusable scoped Event template.
- `POST /event-templates/{template}/events` — schedule an Event from a template.

Mutation routes require authenticated, verified Users and recent password confirmation.

## 3. Calendar outputs

- `GET /events/export.csv` — authorized CSV calendar output.
- `GET /events/feed.ics` — authorized iCalendar output.

See [Calendar exports](calendar-exports.md).

## 4. Scope and authorization

Every read/write resolves the Event scope and exact target (`Player`, `Alliance`, or `Kingdom`) and evaluates the corresponding permission. UI visibility never substitutes for server-side authorization.

## 5. Player context

Event forms never accept an actor Player identifier. The optional author Player is read from the validated server-side active Player context. Self Player-scoped mutations require the active Player to equal the Event target.

## 6. Related interfaces

Participation, polls, rosters, battle planning, results, reminders, and rally operations are added by their owning Events slices and use the same Event/occurrence identity.
