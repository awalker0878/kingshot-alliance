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
- opt-in one-off and recurring announcement intent, schedule lifecycle and immutable run receipts;
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

Content decides whether an Announcement should notify active members. It owns one-off publication intent, timezone-aware recurring rules and one immutable run record for each materialized occurrence. A rule stores ISO weekdays, wall-clock time and an IANA time zone so daylight-saving changes do not silently move the intended local time. Revising or archiving content deactivates its active recurring rule.

Content resolves the active Alliance membership snapshot and submits render-ready delivery intent through the Communications scalar/value-object contract. Recipient preferences, endpoints, provider attempts and retry state remain Communications-owned. The management screen is a cross-context projection in `ReadModels/AnnouncementBroadcastManagement`; the Alliance write controller never imports that projection.

The fanout worker is idempotent per run, Governor and channel. Test delivery targets only the requesting manager. Failed external deliveries can be selected for a bounded retry after Content reauthorizes the run and Communications revalidates the concrete delivery state. Alliance tables never store provider credentials or provider-specific error state.

Notice reactions do not cross this delivery boundary. Like/Dislike never creates a notification, broadcast run or Communications delivery intent.

## Media boundary

Business metadata/lifecycle belongs to Alliance Content. Generic filesystem/object-storage transport is infrastructure. Asynchronous scanning/publishing must remain retry-safe and must not expose private media or storage secrets.
