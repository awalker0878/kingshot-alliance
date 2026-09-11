# Alliance — Content

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Content`

Content owns Alliance-authored content, member Notice reactions and media lifecycle.

## Responsibilities

- content categories/items;
- first-class canonical Alliance Rules using the existing Content item/revision store;
- publish/archive behavior;
- revisions/restore, including broadcast intent and provenance;
- scheduled publishing;
- lightweight Like/Dislike state for published Alliance Notices;
- provenance and review-date requirements for knowledge content;
- revisioned contextual links and policy-derived freshness state for knowledge content;
- opt-in one-off and recurring announcement intent, schedule lifecycle, immutable occurrence identity and bounded durable preparation;
- Alliance public-profile content;
- media upload/archive lifecycle.

## Authority

Management uses Alliance content permissions interpreted by `Alliance/Access` from the active Player's current Alliance authority. Public/member visibility remains a Content policy and does not change aggregate ownership.

Canonical Alliance Rules are managed through the dedicated Content Action and require `ContentManage`. The Rules document has the reserved Alliance-local slug `alliance-rules`, remains member-visible, publishes immediately through that workflow, and continues to use Content revisions/audit/outbox instead of a parallel Rules store. The canonical item is intentionally omitted from the generic management projection, and generic Content save/rename, publish/schedule, archive and revision-restore Actions reject that identity even for crafted requests; the dedicated Rules workflow is the sole mutation path. That Action acquires the Alliance aggregate's exclusive write lock before looking up or creating the canonical Content row, so concurrent first-save requests serialize even when no Rules row exists yet; the `(alliance_id, slug)` unique constraint remains the persistence backstop.

Notice reaction authority is deliberately different. Setting or removing a Like/Dislike revalidates the current active Alliance membership through `AllianceWriteState`, but does not consult `ContentManage` or any publish/edit/archive/broadcast permission. A manager may react because they are an active member, not because they are a publisher.

## Notice reaction boundary

A reaction belongs to exactly one Player + published Alliance `Announcement`. The database enforces one active reaction per pair, and the only stored values are `like` and `dislike`; no neutral row or score is persisted.

A reactable target must still belong to the active Alliance, be published and currently visible to members/public, have reached its publication time, and not be archived. Draft, scheduled, archived, foreign-Alliance and non-Announcement Content is rejected at the owner Action boundary.

Member reads may compose only Like count, Dislike count and the current Player's reaction. These aggregates are display context, not ranking data. They do not alter Content query ordering, visibility, pinning, moderation, notifications, recommendations, reputation or publishing authority. No popularity/engagement read model is owned by Content.

## Provenance boundary

Guides, Event instructions and reference pages require a human-readable source label and review date before save or publication. An optional credential-free HTTPS source URL and game-version label make version-sensitive claims inspectable without treating external community projects as authoritative data.

Provenance is part of the immutable content revision. Restoring a revision restores the associated provenance and broadcast intent, clears publication/broadcast markers and returns the item to draft.

Knowledge content becomes due for review after 90 days and enters the due-soon queue 14 days before that deadline. These repository-controlled defaults are presentation-independent domain policy. A correction saves a new immutable revision and resets freshness only when its reviewed date changes.

Content stores contextual references as allowlisted type/key values. Event references use the stable Event-type slug and are revisioned with the content; no Content table has a foreign key to Operations. The Event Calendar read model resolves only published, member-visible guidance for Alliance-scoped Events. See [ADR-0007](../../adr/0007-version-contextual-knowledge-links.md).

## Delivery boundary

Content owns one-off publication intent, timezone-aware recurring rules and immutable occurrence identity. [ADR-0049](../../adr/0049-bounded-announcement-occurrences.md) defines bounded materialization and recipient work. One-off identity includes revision; recurring identity includes schedule generation and scheduled time. Saving or cancelling recurrence advances its generation. A changed revision or archived source cannot continue an old pending audience.

Materialization creates Pending progress without loading all recipients. Membership provides finite keyset pages with a captured upper bound, not preauthorized recipients. Each recipient is reauthorized against current owner facts in its own transaction; Communications intent and cursor/counters commit atomically. A stale worker cannot advance a moved cursor twice. No enabled route means suppression, not replay. Queued/Empty means recipient preparation completed, not provider success; cancellation preserves earlier delivery history. Natural recurrence exhaustion does not revoke the last valid occurrence.

`broadcasted_at` records durable one-off creation; run `queued_at` means preparation completion. Source visits and recipient work have separate budgets. Persisted visitation priority prevents old sources and large runs from monopolizing low budgets. Indexed queries limit candidates before hydration; no transaction spans the audience or network IO.

The shared occurrence policy applies during enqueue and later external source authorization. Preferences, endpoints, provider attempts and retries remain Communications-owned, with current account/Governor, source, attempt, member and endpoint-generation fences. `ReadModels/AnnouncementBroadcastManagement` composes management presentation; write controllers do not import it. Communications supplies complete retained per-run read/status outcomes through [ADR-0050](../../adr/0050-scoped-announcement-outcome-projections.md), independently from Content preparation counters. Retry selections are bounded and display selected versus total candidates. Catalogue, schedule, media/revision and older-history continuation remain unfinished in HARD-106.

Test delivery stays limited to the requesting current manager. Failed external deliveries can be selected for bounded retry only after Content reauthorizes source/run scope and Communications revalidates concrete state. Alliance tables never store provider credentials or provider-specific errors.

Notice reactions do not cross this delivery boundary. Like/Dislike never creates a notification, broadcast run or Communications delivery intent.

## Media boundary

Business metadata/lifecycle belongs to Alliance Content. Generic filesystem/object-storage transport is infrastructure. Asynchronous scanning/publishing must remain retry-safe and must not expose private media or storage secrets.
