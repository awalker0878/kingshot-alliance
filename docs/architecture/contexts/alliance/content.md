# Alliance — Content

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Content`

Content owns Alliance-authored content and media lifecycle.

## Responsibilities

- content categories/items;
- publish/archive behavior;
- revisions/restore, including broadcast intent and provenance;
- scheduled publishing;
- provenance and review-date requirements for knowledge content;
- opt-in announcement broadcast intent and the completed-fanout marker;
- Alliance public-profile content;
- media upload/archive lifecycle.

## Authority

Management uses Alliance content permissions interpreted by `Alliance/Access` from the active Player's current Alliance authority. Public/member visibility remains a Content policy and does not change aggregate ownership.

## Provenance boundary

Guides, Event instructions and reference pages require a human-readable source label and review date before save or publication. An optional credential-free HTTPS source URL and game-version label make version-sensitive claims inspectable without treating external community projects as authoritative data.

Provenance is part of the immutable content revision. Restoring a revision restores the associated provenance and broadcast intent, clears publication/broadcast markers and returns the item to draft.

## Delivery boundary

Content decides whether an Announcement should notify active members and owns the publication/broadcast marker. It resolves the active Alliance membership snapshot and submits render-ready delivery intent to Communications. Recipient preferences, endpoints, provider attempts and retries remain Communications-owned.

The fanout worker is idempotent per Announcement, Governor and channel. It never stores provider credentials or provider-specific state in Alliance tables.

## Media boundary

Business metadata/lifecycle belongs to Alliance Content. Generic filesystem/object-storage transport is infrastructure. Asynchronous scanning/publishing must remain retry-safe and must not expose private media or storage secrets.