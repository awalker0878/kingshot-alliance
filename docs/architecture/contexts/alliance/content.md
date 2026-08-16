# Alliance content and media

Status: Current  
Context: Alliance  
Implementation: `app/Contexts/Alliance/Content`

Alliance Content owns Alliance-authored content and its media lifecycle.

## Current behavior represented in code

The capability includes content categories/items, publish/archive behavior, revisions/restore, scheduled publishing, Alliance public-profile content and media upload/archive lifecycle.

## Authority

Management uses Alliance content permission derived from the active Player's Alliance authority. Public/member visibility is a content policy; it does not make the content aggregate globally owned.

## Media boundary

Business metadata/lifecycle belongs to Alliance Content. Generic filesystem/S3 transport is infrastructure. Production private/content media must use the configured durable/private storage boundary described in system operations/security governance.

Asynchronous scanning/publishing work must remain retry-safe and must not expose private media or secret storage details.