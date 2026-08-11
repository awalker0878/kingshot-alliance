# Content interfaces

[← Content domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Content  
**Code owner:** `app/Domain/Content`  
**Primary boundary:** Public/member content presentation, manager authoring/publication, and screened private-media presentation  
**P4 inventory decision:** Focused contract reused — `../media.md`

## 1. Boundary purpose and ownership

Content owns authored Alliance content and the supported presentation boundary from private draft/member state to intentionally published public/member output. It also owns private Content media and the controlled path by which approved media may be presented as Alliance branding.

Public Alliance presentation is Content-owned even though it includes approved Alliance identity and Recruitment availability facts supplied by their owning domains.

## 2. Surface inventory

Public first-party routes in `routes/web.php` include:

- `GET /alliances/{slug}` — public Alliance/profile presentation;
- `GET /alliances/{slug}/content/{contentSlug}` — published public content; and
- `GET /alliances/{slug}/branding/{slot}` — approved `logo` or `banner` media.

Authenticated active-Alliance surfaces include member content list/detail, Content management, public-profile update, category management, content create/edit/publish/archive/revision restore, and media upload/archive.

Scheduled publication is exposed operationally through `content:publish-scheduled`.

## 3. Callers, authorization and tenancy

Anonymous callers may read only public profile/content/branding explicitly allowed by Content publication/visibility/media rules.

Member reads require authenticated, verified active-Alliance context. Manager authoring/publication/media mutations require `content.manage` plus the recent password-confirmation boundary applied by the owning routes. Submitted category/content/revision/media IDs are resolved beneath the active Alliance.

## 4. Input and validation contracts

Authoring inputs are validated for supported content/category/public-profile fields; authored body content is not treated as trusted arbitrary HTML. Slugs use the route-safe lowercase-hyphen format where exposed publicly.

Media input validation, scanner policy, storage state, allowed use, and archival semantics are owned by [Content media](../media.md). A failed scanner/storage dependency does not permit an upload to bypass usable-state checks.

## 5. Output and disclosure contracts

Anonymous output contains only explicitly published/public-safe content and usable approved branding. Draft, scheduled-but-not-yet-published, archived, members-only, revision-history, private media metadata, and manager-only fields do not cross the public boundary.

Member payloads may include member-visible content while management payloads may include editing/revision/media state according to permission. Content does not expose a public write API.

## 6. Internal actions, queries and services

Supported internal contracts include content/public-profile queries, publication/authoring actions, and the media services defined in [Content media](../media.md). Content may consume Recruitment availability for public presentation but does not own or mutate Recruitment settings.

Other domains should consume Content presentation/services rather than reaching into Content persistence to publish drafts or bypass revision/media invariants.

## 7. Events, outbox and cross-domain consumers

Material Content transitions may produce audit/outbox evidence. Producer semantics remain Content-owned; Platform owns outbox publication. Generic outbox publication does not create a public Content webhook or write API.

Any externally delivered webhook for a Content-originated event is subject to the separate Integrations external-eligibility/envelope contract.

## 8. Commands, jobs and scheduled work

`content:publish-scheduled {--limit=100}` invokes the bounded scheduled-publication action. The scheduler runs `content:publish-scheduled --limit=100` every minute with one-server/overlap protection.

Safe catch-up uses persisted publication state and the owning action; operators do not mark rows public manually. See [Content operations](../operations/README.md) for recovery detail.

## 9. Files, imports, exports and external dependencies

The material file boundary is private Content media. [Content media](../media.md) documents upload/storage/scanning/usable/archive/public-branding semantics.

Externally relevant dependencies include private object/media storage and the configured media scanner. Public content text itself is PostgreSQL backed. There is no accepted bulk Content import/export interchange format in P4.

## 10. Failure, idempotency, versioning and compatibility

Missing/inactive Alliance, invalid tenant-owned identifiers, unpublished visibility, scanner/storage failure, or unusable media fails closed. Public URLs/slugs and branding slot names are externally observable compatibility contracts; changes require coordinated route/document/test review.

Scheduled publication is state-driven and safely bounded; public reads never infer publication from timestamps while persisted state remains ineligible.

## 11. Explicit non-capabilities

Content does not:

- accept arbitrary trusted HTML;
- expose draft/member-only/revision-history data anonymously;
- own Recruitment application availability;
- provide a public Content write API;
- bypass media scanning/storage safety; or
- turn every Content outbox event into an external webhook.

## 12. Focused contracts, evidence and related documentation

P4 reuses the accepted [Content media](../media.md) capability contract instead of duplicating it inside `interfaces/`.

Related documentation:

- [Content domain contract](../README.md)
- [Content media](../media.md)
- [Content security](../security/README.md)
- [Content operations](../operations/README.md)
- [Scheduled publishing and media runbook](../operations/scheduled-publishing-and-media.md)
- [Recruitment](../../recruitment/README.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
