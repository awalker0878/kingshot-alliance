# Content domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Content`  
**Primary authorization boundary:** `content.manage`

## 1. Purpose and ownership

Content owns Alliance-authored public/member content, categories, immutable revision history, publication/visibility state, and private Content media used by supported presentation surfaces.

Recruitment remains authoritative for application availability; Content may present that state but does not own a duplicate writable recruitment flag.

## 2. Scope

In scope: authored content, categories/order/locale, draft/scheduled/published/archived state, revision history, public/member-safe search/filtering, public profile presentation fields, and Content media.

Out of scope: Recruitment state, Event/Rally scheduling, Alliance membership/RBAC, Platform entitlement ownership, and trusted arbitrary HTML.

## 3. Domain model

Content items have type, visibility, title/slug, optional summary/body/locale/category/order, and publication lifecycle. Revisions are immutable historical snapshots created on save/restore workflows.

Private media has a distinct storage/security lifecycle documented in [Content media](media.md).

## 4. Core invariants

1. All Content management is Alliance scoped.
2. New/edited/restored content requires explicit publication before public visibility.
3. Editing published content creates a new revision and returns it to draft.
4. Restoring history creates a new draft; historical rows remain immutable.
5. Members-only/unpublished/archived/revision-history records never cross the public boundary.
6. Authored body text is not treated as trusted arbitrary HTML.
7. Recruitment availability is not duplicated in Content persistence.
8. Media presentation follows the [media lifecycle](media.md).

## 5. Lifecycles and workflows

Managers create categories/content, save revisions, publish immediately or schedule publication, edit published content back to draft, restore historical revisions into new drafts, and archive items without deleting history.

Scheduled publication uses the shared scheduler. Search/filtering begins from the caller's public/member visibility boundary.

Media upload/screening/branding selection/archival is documented in [Content media](media.md).

## 6. Authorization and tenancy

Management requires authenticated, verified active-Alliance context plus `content.manage`; privileged mutations use required recent password confirmation. Submitted Content/category/media IDs are re-resolved under the active Alliance.

## 7. Cross-domain contracts

Consumes Alliances tenant/presentation context, Authorization, Recruitment availability, Platform shared limits/infrastructure, and Audit/outbox evidence.

Exposes published public/member-safe Content presentation and supported Content management workflows.

## 8. Persistence and data ownership

Content owns categories, content items, revisions, and media records. Recruitment, Memberships, Identity, Events, and other domains retain their own persistence.

## 9. Events, outbox and integrations

Material privileged Content transitions may create audit/outbox evidence. No public Content write API is approved by generic Integrations behavior.

## 10. HTTP, UI and API surfaces

First-party surfaces include Alliance Content management plus public/member presentation/search. Public reads expose only records allowed by visibility/publication state.

## 11. Background processing

Scheduled publication uses the application scheduler. Content has no hidden ingestion worker; media runtime dependencies are covered by [Content media](media.md).

## 12. Failure, idempotency and concurrency

Invalid tenant IDs fail closed, category deletion fails while referenced, and public visibility fails closed unless publication/visibility criteria are satisfied. Scanner/storage failures do not permit bypass of media lifecycle controls.

## 13. Security and privacy

Draft/member-only/revision-history data and private media remain tenant private. Content-authoring authority is not authority to publish Recruitment/member private data.

## 14. Observability and operations

Diagnose publication scheduler, content state, media storage/screening, and tenant authorization separately. See shared [Operations](../../operations/README.md).

## 15. Testing and architecture enforcement

Tests protect publication/revision/restore, public-versus-member visibility, tenant search/filtering, category constraints, scheduled publication, and [media lifecycle](media.md) ownership/security rules.

## 16. Explicit non-capabilities

Content does not own Recruitment availability, trust arbitrary HTML, expose unpublished/member-only data publicly, bypass media screening, or redefine tenancy/RBAC.

## 17. Capability documents

- [Content media](media.md) — private storage, upload validation/screening, usable/archived state, and branding attachment safety.

## 18. Related documentation

- [Recruitment](../recruitment/README.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Platform](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Content/README.md`](../../../app/Domain/Content/README.md)
