# Kingdom Map workspace delivery ledger

## Resume header

- Status: **In progress; not merge-ready.** Keep this implementation's pull request draft until every required scope and final gate is evidenced.
- Branch: `astra/kingdom-map-workspace`.
- Baseline: `main` at `044a6be16e54b3bc2ee5ae9ca9adf6a9c9c5923c`.
- Current work: obtain an exact local checkout and locked Node 24/PHP 8.5 verification tools; audit existing behavior and implement dependency-ordered vertical slices.
- Durable setup: `3197248b7cd2e74f4543098ee1e98f2c9ecb40fb` adds read-only, branch-scoped source/tooling transport. It exports only tracked Git objects and lockfile-selected tools, not runtime environment files or credentials. Remove this temporary workflow before completion.
- Verification: no application checks have yet been executed for this delivery. Existing passing commits do not certify the current candidate.
- External source reconciliation: the requested shared conversation could not be fetched through the available web reader. Its body and any decisions unique to it remain unverified. The fully retrieved original planning prompt and supplied execution instructions establish the independently verified scope below. No unseen work-package identifiers or renderer decisions are claimed.
- Concurrent ownership: draft PR #164 (`astra/hardening-followup`) continues repository-wide hardening separately. Do not overwrite that branch, its ledger, or its owners. Resolve or integrate necessary inherited release blockers with exact evidence; do not declare the separate hardening program complete.
- Next action: inspect map/editor/server/test/asset contracts from the exact source artifact and implement the first missing complete workflow, preserving V2-only facts and authority.

Checkpoint SHAs name preceding durable work. Git history and the PR verification receipt identify the exact current candidate without circular self-reference.

## Sources and boundaries

The delivery implements the user's **Kingshot Kingdom Map — Complete Implementation Planning Prompt** and **Kingshot Kingdom Map — Execute to Completion** instructions. The shared plan reference is <https://chatgpt.com/share/6aa60306-ebe0-83ea-b549-676c3f7b7c3e>; inaccessible content remains an explicit source gate, not an excuse to stop independent implementation.

Architecture ownership remains defined by [ADR 0009](../architecture/adr/0009-versioned-map-truth-and-territory-planning.md), [KingdomMaps](../architecture/contexts/game-world/kingdom-maps.md), and [TerritoryPlanning](../architecture/contexts/operations/territory-planning.md). GameWorld owns immutable facts; Operations owns plans and publication; Intelligence owns observations/evidence; ReadModels compose authorized reads. Artwork is presentation, not collision geometry or evidence of mechanics.

This is a new deployment: one canonical contract, no obsolete schema readers, compatibility aliases, dual reads/writes, upgrade shims, or parallel authorities. Historical immutable releases/revisions, safe loading fallbacks, current integrations, and supported-browser behavior remain legitimate requirements. Destructive schema verification is restricted to disposable test databases.

The user supplied authorization for Kingshot artwork. Record actual source files and provenance; do not extend that authorization to unrelated third-party code/data. Placeholder or fallback artwork does not satisfy final required asset coverage.

## Complete acceptance queue

These `KMAP` identifiers are newly assigned delivery identifiers, not claimed identifiers from the unread shared conversation. Every requirement must receive concrete implementation paths and executed evidence as work proceeds.

| ID | Required outcome | Current state | Acceptance / remaining evidence |
| --- | --- | --- | --- |
| KMAP-001 | Source, baseline, ownership, existing-behavior audit and durable continuation | In progress | Exact checkout; reconciled source plan; traced UI/server/data/test behavior; no unsupported completion labels. |
| KMAP-002 | Canonical V2 contracts and fresh installation | Unverified | All owned callers agree; obsolete inputs rejected; clean disposable deployment without repair/upgrade shims. |
| KMAP-003 | Complete evidence-supported object/layer catalogue and actual data delivery | Unverified | Fixed structures/facilities/terrain/resources/regions/exclusions rendered from materialized authorized records; partial/unavailable coverage explicit; no invented coordinates. |
| KMAP-004 | Authorized artwork pipeline and unified versioned asset registry | Unverified | Every required family/variant has palette icon, sprite, detail picture; manifest provenance/checksums/anchors/variants; safe preparation/loading/caching and export parity. Missing source art remains a gate. |
| KMAP-005 | One canonical renderer and geometry fidelity | Unverified | Culling/hit testing/redraw/HiDPI/zoom detail/bounded memory; correct origins/axes/rotation/edges; PHP/TypeScript parity; art separate from footprint/coverage/hit target. |
| KMAP-006 | Kingdom Explorer across desktop/tablet/mobile | Unverified | Search, inspector, layers/legends, minimap, coordinates/jump, bookmarks/saved views, fit controls/full-screen, keyboard/pointer/touch, explicit modes and all failure/read-only states. |
| KMAP-007 | Complete editing and authorized persistence | Unverified | Place/move/duplicate/delete, coordinates/snap/preview, multiselect/group/legal transformations/locks/alignment, undo/redo; expected revisions, server validation, retries/conflicts/recovery; no pointer-event write flood. |
| KMAP-008 | Hive templates and Governor assignments | Unverified | Bear-centered generation, layouts/templates, slots/reservations, real roster and plan-local identities, alternative comparison and visible assumptions. |
| KMAP-009 | Explainable analysis and accepted suggestions | Unverified | Coverage/connectivity/resource access/Banner efficiency/density/distances; reproducible constrained proposals; explicit preview/accept; sourced mechanics and honest limitations. |
| KMAP-010 | Plan versus Observed Reality | Unverified | Authorized pinned comparisons, freshness/coverage/uncertain matches, moved/unexpected/missing-vs-not-observed distinctions; no implicit overwrite of planning intent. |
| KMAP-011 | Multi-alliance collaboration and review | Unverified | Independent identities/scope, object comments, assignments, revision notes, review/publication, optimistic concurrency; no unsupported live-collaboration claim. |
| KMAP-012 | Real owner integrations and bounded operations | Unverified | Roster/event/audit/Intelligence/notification entry points invoked; preferences/current authority/idempotency/retries/bounded fan-out; commands/queues/scheduling/storage/diagnostics wired. |
| KMAP-013 | Canonical import, artwork-bearing exports and private sharing | Unverified | Validated preview/dry run/duplicate handling/commit; JSON round trip and old-format rejection; PNG/SVG viewport/selection/alliance/map fidelity; limits/cleanup/failures; private scoped revocable sharing. |
| KMAP-014 | Security, accessibility, localization and measured performance | Unverified | Scope switching/revocation/stale tabs/cross-tenant/cache/export/input tests; semantic object list/focus/keyboard/touch/announcements/non-color cues; long labels/RTL; measured budgets on real and labeled synthetic fixtures. |
| KMAP-015 | Exact-candidate release assurance and cleanup | Open | All applicable normal gates, clean-install evidence, desktop/tablet/mobile visual inspection, performance receipts, obsolete-path/placeholder/dependency/docs audit, temporary tooling removal, full requirement reconciliation. |

## Evidence policy

Implementation, narrow validation, full validation, merge readiness, merge, and deployment are separate claims. Each completed item must name actual files, tests, data/assets, execution results, and a containing immutable SHA. Authored or queued checks are not passing checks. Update affected evidence after changes and retain exact run/artifact references in the PR receipt.

Final gates include applicable PHP/style/static analysis/architecture, frontend/lint/types/build, geometry and dataset/artifact contracts, authorization/integration, browser/visual, dependency/security, fresh-schema, container/staging/recovery, and source/artwork/data completeness. Review screenshots; do not blindly bless baseline changes. Repair root causes rather than disabling or weakening gates.

Missing external sources, artwork, or permissions require exact unblock information and continued independent work. A hard execution limit permits a durable **NOT COMPLETE** checkpoint, never a fabricated completion claim.
