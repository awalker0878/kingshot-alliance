# Kingdom Map workspace merge ledger

## Merge state

- **Status:** Merged to `main`.
- **Pull request:** #165 — `Implement complete Kingdom Map workspace with canonical V2 contracts`.
- **Merge commit:** `378db5b9681aaa67f4703195987903c99e064de0`.
- **Merged head:** `9e495705a72c0d5244b7e3c8612af2eb4ee02350`.
- **Current `main`:** `378db5b9681aaa67f4703195987903c99e064de0` at ledger creation time.
- **Former feature branch:** `astra/kingdom-map-workspace`, now one commit behind `main` because `main` contains the merge commit. It is **not missing the feature commits**.

## Commit reconciliation

The Kingdom Map implementation commits immediately preceding the merge were inspected directly in repository history:

| Commit | UTC | Purpose | Reconciled into `main` |
| --- | --- | --- | --- |
| `2fd7d75a6c9376c173980151bdfe9017caeac297` | 2026-09-14 02:24 | Reconcile complete source plan and audited PR #165 baseline | Yes |
| `aa88d4d11d7dc786b0d262ff16e038d8098c6ee8` | 2026-09-14 01:49 | Complete async collaboration and private share viewer | Yes |
| `c18f672ea1bd26b4a1627ffbd8218de23f1cf94d` | 2026-09-14 07:23 | Restore owner boundaries and verification gates | Yes |
| `9e495705a72c0d5244b7e3c8612af2eb4ee02350` | 2026-09-14 12:58 | Unify scene rendering and complete Explorer traversal | Yes |
| `378db5b9681aaa67f4703195987903c99e064de0` | 2026-09-14 18:11 | Merge PR #165 to `main` | Yes |

GitHub comparison confirms `main` is **0 commits ahead / 1 commit behind** `astra/kingdom-map-workspace`; the sole difference is the merge commit. Therefore the feature branch's implementation commits are already reachable from `main`.

## What the final feature commit contained

`9e495705` reconciled the latest Kingdom Map work into the merged candidate, including:

- one indexed Canvas/SVG presentation path;
- exact terrain spans, rotated geometry and coverage;
- selected/observed rendering;
- grid, labels, collision suppression and viewport culling;
- spatial geometry lookup;
- hidden-layer hit-testing protection;
- atomic locked-selection behavior;
- bounded Explorer pagination and traversal;
- saved-view management;
- mobile panels/fullscreen controls;
- coordinate validation and request fencing;
- 17-locale Explorer label coverage;
- canonical browser JSON schema 2 with explicit `head_revision`;
- TypeScript/PHP interchange fixture verification;
- shared Canvas/export layer fidelity;
- working-draft identification in visual exports;
- bounded/deduplicated raster embedding;
- lazy export loading to satisfy the existing page-size budget;
- behavioral coverage for terrain holes, Y-up painting, semantic traversal, offscreen previews, hidden layers, label priority, rotation and browser/PHP interchange.

## Important distinction

The earlier delivery ledger in `docs/product/kingdom-map-workspace-delivery-ledger.md` was intentionally written before completion and still says `In progress; not merge-ready`. That wording is now historical and must not be interpreted as evidence that PR #165's commits are absent from `main`.

The merge itself does **not** prove every product-level acceptance item from that historical ledger was independently revalidated after the final renderer/Explorer commit. Those claims require their own current evidence. This ledger only reconciles commit reachability and merge state.

## Verification boundary

- PR #165 is already merged; it cannot be merged a second time.
- No feature commit from the final PR head needs to be copied or cherry-picked into `main`.
- Any remaining Kingdom Map acceptance gaps must be implemented as new commits from current `main` and delivered through a new pull request.
- This ledger deliberately does not convert historical acceptance gaps into false completion claims.
