# Alliance — Content

Status: Current — Architecture V3

Implementation target: `app/Contexts/Alliance/Content`

Content owns Alliance-authored content and media lifecycle.

## Responsibilities

- content categories/items;
- publish/archive behavior;
- revisions/restore;
- scheduled publishing;
- Alliance public-profile content;
- media upload/archive lifecycle.

## Authority

Management uses Alliance content permissions interpreted by `Alliance/Access` from the active Player's current Alliance authority. Public/member visibility remains a Content policy and does not change aggregate ownership.

## Media boundary

Business metadata/lifecycle belongs to Alliance Content. Generic filesystem/object-storage transport is infrastructure. Asynchronous scanning/publishing must remain retry-safe and must not expose private media or storage secrets.