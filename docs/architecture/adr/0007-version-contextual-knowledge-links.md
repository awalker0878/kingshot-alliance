# ADR-0007: Version contextual knowledge links with content

Status: Accepted

Date: 2026-08-20

## Context

Alliance guides and Event instructions need to appear where Governors use them, but Content must not own Operations Event models or create a cross-context foreign-key dependency. A correction or revision restore must also restore the exact source and Event association that readers previously reviewed.

## Decision

1. Content owns generic `context_links` values made of an allowlisted context type and normalized key.
2. Event links use `event_type` plus the stable Event-type slug; Content never stores an Operations model or foreign key.
3. Context links are copied into every immutable Content revision and restored with provenance, body and broadcast intent.
4. `ReadModels/AnnouncementBroadcastManagement` supplies current Event-type choices to the editor.
5. `ReadModels/EventCalendar` resolves published member-visible Content for Alliance Events and presents it as contextual guidance.
6. Knowledge freshness is derived from the versioned review date using the repository-controlled review window. The manager projection exposes current, due-soon and overdue states.

## Consequences

- Content remains the sole writer and Operations remains unaware of Content persistence.
- A deleted or renamed Event type cannot corrupt Content rows; unresolved keys simply produce no Event guidance until corrected.
- Review corrections preserve a complete source-and-context history.
- Event pages can expose trusted guidance without copying strategy text into Event records or Vue components.

## Rejected alternatives

- A foreign key from Content to Operations was rejected because it would couple context lifecycles and ownership.
- Repeating Event instructions inside Event settings was rejected because corrections and provenance would diverge.
- Computing links from titles or tags was rejected because contextual placement must be explicit and inspectable.
