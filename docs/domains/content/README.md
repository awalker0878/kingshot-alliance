# Content domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Content`  
**Primary authorization boundary:** `content.manage`

## 1. Purpose and ownership

Content owns alliance public presence, authored alliance content, categories, revisions, visibility/publication state, media assets, and the public/member presentation rules for that content.

The management workflow is designed for alliance owners, leaders, and content managers who have `content.manage`. All alliance-owned changes remain scoped to the active Alliance.

## 2. Scope

### In scope

- public alliance profile presentation fields;
- announcements, guides, rules, event instructions, and reference pages;
- categories, ordering, locale, and visibility;
- draft, scheduled, published, archived, and revision behavior;
- private tenant-scoped media upload and lifecycle;
- public/member search and filtering; and
- content-management UI and publication scheduling.

### Out of scope

- recruitment availability state, which is authoritative in Recruitment;
- event/rally scheduling and event-state ownership;
- alliance membership and authorization policy ownership;
- platform plan/entitlement ownership; and
- treating authored body text as trusted HTML.

## 3. Domain model

### Public alliance profile

The Content-managed public presentation includes alliance description and branding presentation such as primary branding color, logo, and banner. Alliance-owned identity/settings such as name, Kingdom, language, and time zone may be presented through the public profile but remain owned by the appropriate Alliance/Kingdom contracts.

Only clean, active image media belonging to the current Alliance can be selected for logo or banner presentation.

Recruitment availability is not a content-profile setting. Recruitment settings remain authoritative for whether applications are closed, public, or invitation-only, and the public alliance page reads that state rather than maintaining a duplicate writable Content field.

### Categories

Categories organize alliance content and control ordering in browse/filter views. A category has a display name, URL-safe slug, and optional sort order. A category cannot be deleted while content still references it.

### Content items

Supported content types are:

- announcements;
- guides;
- rules;
- event instructions; and
- reference pages.

A content item may define public or members-only visibility, title, URL slug, optional summary, body text, locale/language tag, optional category, and sort order.

### Revisions

Every content save creates immutable revision history. Revision history is retained across edits, restore, archival, and later republication.

### Media

Media assets are Alliance-owned private records backed by tenant-specific storage. Production uses durable S3-backed storage. Upload acceptance is constrained by configured MIME type, size, and security-screening behavior.

## 4. Core invariants

1. All Content management occurs beneath the active Alliance boundary.
2. Saved new content starts as a draft.
3. Authored text is stored/rendered as plain text rather than trusted HTML.
4. Editing a published item creates a new revision and returns the item to draft; an unreviewed edit never becomes public automatically.
5. Restoring a historical revision creates a new draft revision and never silently republishes historical text.
6. Members-only content is never exposed through the public alliance page.
7. Draft, not-yet-published scheduled, archived, and revision-history records are excluded from public search.
8. A current logo/banner asset must be detached from the public profile before that asset can be archived.
9. Recruitment state is never duplicated as writable Content persistence.

## 5. Lifecycles and workflows

### Open the content manager

1. Sign in and verify the email address.
2. Switch to the Alliance to manage.
3. Open the alliance overview.
4. Choose **Manage content**.
5. Reconfirm the password when requested before privileged changes.

### Public profile and branding

Upload branding media first, select the clean active image for the logo/banner slot, and save the public profile. Content never bypasses media lifecycle/security state to publish an unapproved asset.

### Create and organize content

Managers may create categories, then create content with type, visibility, title/slug, summary, body, locale, category, and ordering. New content remains draft until explicitly published or scheduled.

### Publish now or schedule

A draft may be published immediately or scheduled for a future time. Scheduled publication is processed by the application scheduler. Public visibility begins only after the publication transition succeeds.

### Edit published content

Editing a published item creates a new revision and returns the item to draft. The manager must explicitly publish or schedule the revised item.

### Restore revision

Restore copies the selected historical revision into a new draft revision. It never changes the historical row and never implicitly publishes.

### Archive

Archiving removes an item from public/member browse results without deleting revision history. An archived item can later be edited/restored into draft form and explicitly republished.

### Media upload

Uploads are limited by configured MIME type and size; the documented default maximum is **8192 KiB**. Files are security-screened before a media record becomes usable. Rejected files are not retained.

### Search and filtering

Public/member hubs can filter by search text, type, category, and locale. Search begins from the caller's visibility boundary:

- anonymous visitors: published public content only;
- active members: published public content plus published members-only content.

### Date/time behavior

Publication timestamps are stored as absolute timestamps and displayed in the viewer's browser locale. Alliance time-zone information remains visible so alliance-specific timing is explicit.

## 6. Authorization and tenancy

Content management requires authenticated, verified active-Alliance context and `content.manage`. Privileged mutations use recent password confirmation at the HTTP boundary.

All submitted Content/category/media identifiers are re-resolved under the active Alliance. Public/member reads begin from their visibility boundary rather than loading arbitrary content and filtering afterward.

## 7. Cross-domain contracts

### Consumes

- **Alliances** — active Alliance context and Alliance-owned profile/settings presented by the public surface.
- **Authorization** — `content.manage` and ordinary Alliance-view authorization.
- **Recruitment** — authoritative recruitment availability displayed publicly.
- **Platform** — plan/storage controls and shared audit/outbox/platform infrastructure where applicable.
- **Audit** — attributable privileged-change evidence.

### Exposes

Content exposes public/member-safe published presentation data and the intentional management workflows owned by this domain. Other domains must not mutate Content persistence directly.

## 8. Persistence and data ownership

Content owns categories, content items, immutable revisions, and media records. Media paths are tenant-specific/private. Historical revisions remain available after normal editing and archival.

Content does not own Recruitment settings, membership authorization persistence, or global user identity.

## 9. Events, outbox and integrations

Material privileged content transitions are auditable and may use the shared transactional-outbox foundation for durable downstream work. Publication state remains authoritative in Content.

No public machine-to-machine Content write contract is implied by the generic Integrations subsystem.

## 10. HTTP, UI and API surfaces

The primary first-party management surface is **Alliance → Manage content**. Public and member alliance surfaces render only records allowed by their visibility/publication state.

There is no documented public Content write API contract.

## 11. Background processing

Scheduled publication is handled by the application scheduler. A scheduled record becomes public only after a successful publication transition.

Content does not define a separate hidden content-ingestion worker.

## 12. Failure, idempotency and concurrency

- If **Manage content** is unavailable, verify active Alliance, active membership, `content.manage`, and verified email.
- If a mutation redirects to password confirmation, confirm the password and retry.
- If an upload is rejected, verify MIME type/size and use a clean source file.
- Persistent scanner/storage failures are operator issues and must not be bypassed by direct persistence edits.
- Category deletion fails while content still references the category.
- Public visibility fails closed when publication/visibility conditions are not satisfied.

## 13. Security and privacy

Member-only, draft, scheduled-unpublished, archived, and revision-history data must not cross the public visibility boundary. Private media storage and upload screening are security controls, not optional presentation behavior.

Content managers must not copy private Recruitment or member data into public content merely because they can author a page.

## 14. Observability and operations

Operators should diagnose scheduler, media storage, and upload-screening failures through the shared operations guidance rather than manually changing Content state.

See [Background processing](../../operations/background-processing.md), [Runtime configuration](../../operations/configuration-reference.md), and the [operations index](../../operations/README.md).

## 15. Testing and architecture enforcement

Tests should protect:

- publication permission and draft visibility;
- public-versus-member visibility boundaries;
- revision/restore behavior;
- tenant-scoped search/filter behavior;
- upload validation and media ownership;
- scheduled publication behavior; and
- the architectural rule that Recruitment owns recruitment availability rather than Content.

Repository/domain boundaries are additionally protected by the architecture test suite.

## 16. Explicit non-capabilities

Content does not:

- own Recruitment application availability;
- treat arbitrary HTML as trusted authored content;
- expose member-only or unpublished records publicly;
- bypass private media/security screening; or
- redefine Alliance tenancy or authorization.

## 17. Capability documents

No separate Content capability documents are required at present. This root contract owns the current Content behavior.

## 18. Related documentation

- [Domain documentation](../README.md)
- [Recruitment domain](../recruitment/README.md)
- [Alliances domain](../alliances/README.md)
- [Authorization domain](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Operations index](../../operations/README.md)
- [`app/Domain/Content/README.md`](../../../app/Domain/Content/README.md)
