# ADR-0059: Bounded integration management history

Status: Accepted

## Context

Connections loaded all credentials and webhook subscriptions even though revoked history has no active-capacity bound. It showed only the latest 50 deliveries with no continuation to older failed attempts. Frontend active counts used those arrays and treated expired credentials as active; delivery names depended on the currently loaded subscriptions.

## Decision

Integrations owns one read-only management query for independently paged credential, webhook and delivery catalogues. Each read reauthorizes the current manager and binds its signed cursor to the actor, Alliance and catalogue kind. Pages contain at most 25 records with one lookahead record, ordered by descending immutable ID. The first page fixes an upper ID frontier; new records appear on refresh, while deletion of a boundary does not restart traversal. Separate SQL totals describe the current complete scope rather than the loaded page.

IntegrationUsageQuery owns the active credential and subscription count predicates, shared by quota enforcement, Platform usage capture and the Connections display. It excludes expired credentials. Page rows expose the current active state explicitly.

Delivery pages resolve only their own bounded, Alliance-scoped subscription names. Payloads and signing secrets are excluded from the management projection. Retry availability reflects the current runtime switch, terminal status, retained payload and active subscription; the existing locked RetryWebhookDelivery action remains authoritative at submission.

The HTTP adapter composes these pages with existing settings and creation forms. Three independent cursors preserve other current page positions. The pager retains current rows on a failed request, offers retry and cancels stale requests when principal scope changes. Existing forms retain drafts across pagination and clear them when the actor or Alliance changes. Revocation errors remain visible with the confirmation open. All supported locales include history, expired and queued states.

Canonical fresh-schema indexes support Alliance/ID traversal. There is no parallel mutation path, historical backfill or alternate permission authority.

## Verification

Seven feature cases cover all three 61-record catalogues, materialization bounds, exact active counts, absence of secrets/payloads, independent HTTP composition, changed retry facts, deleted boundaries, new insertions, current membership revocation and cursor scope/kind isolation. Separate desktop/mobile browser fixtures exercise independent pages, draft retention, an injected page failure and retry, expired state and manual retry of an older delivery whose subscription is outside the current subscription page. Hosted evidence remains recorded under HARD-111.
