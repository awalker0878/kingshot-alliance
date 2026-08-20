# Content domain

## Purpose

Owns Alliance-authored public/member content, revisions, publication/visibility, categories, media assets, and public-presentation content managed by the Content module. Knowledge sources, review dates and generic contextual links are revisioned together; freshness is derived from repository policy.

## Owned code

Runtime code in this module owns Content records/revisions/categories/media, publication/scheduling behavior, content queries, and first-party Content management/public presentation surfaces.

## Public contracts

- published public/member-safe Content projections;
- `content.manage` protected management actions;
- private tenant-scoped media lifecycle; and
- Content presentation of Recruitment-owned application availability without duplicating that state.
- stable context-link values consumed by cross-context read models without importing their domain models.

## Dependencies

- `Alliances` — active tenant and Alliance presentation context.
- `Authorization` — `content.manage` / Alliance-view decisions.
- `Recruitment` — authoritative recruitment availability shown publicly.
- `Audit` / Platform outbox — privileged-change evidence.

## Canonical documentation

- [`docs/domains/content/`](../../../docs/domains/content/README.md)
