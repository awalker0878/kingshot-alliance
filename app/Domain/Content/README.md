# Content domain

## Purpose

Owns Alliance-authored public/member content, revisions, publication/visibility, categories, media assets, and public-presentation content managed by the Content module.

## Owned code

Runtime code in this module owns Content records/revisions/categories/media, publication/scheduling behavior, content queries, and first-party Content management/public presentation surfaces.

## Public contracts

- published public/member-safe Content projections;
- `content.manage` protected management actions;
- private tenant-scoped media lifecycle; and
- Content presentation of Recruitment-owned application availability without duplicating that state.

## Dependencies

- `Alliances` — active tenant and Alliance presentation context.
- `Authorization` — `content.manage` / Alliance-view decisions.
- `Recruitment` — authoritative recruitment availability shown publicly.
- `Audit` / Platform outbox — privileged-change evidence.

## Canonical documentation

- [`docs/domains/content/`](../../../docs/domains/content/README.md)
