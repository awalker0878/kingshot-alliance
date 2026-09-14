# Kingdom Map workspace

Status: In progress — Architecture V3. [Delivery and evidence ledger](kingdom-map-workspace-delivery-ledger.md) owns scope and readiness; this page describes the implemented interaction contract. This is not certification of the complete target workspace.

## Interaction ownership

`TerritoryCanvas.vue` keeps camera and pointer previews local. `engine/viewport.ts` owns presentation coordinate transforms and gesture completion. It does not own map truth, placement legality, persistence, or authorization. Existing TerritoryPlanning Actions remain save authority.

World coordinates are south-west anchored, with positive Y upward. A pan is derived from the camera at pointer-down, not repeatedly from coordinates transformed by an already changed camera. Zoom preserves the world coordinate under the cursor; a pinch also follows the changing midpoint. Resizing preserves the current camera after the initial fit.

A gesture is bound to its initiating pointer and captures its proposed movable selection. Locked and hidden Alliance layers are excluded again at completion. Selection and previews are not permission checks; server authority must still be revalidated by a save.

## Cancellation and navigation

Pointer cancellation, lost capture, window blur, tool/scope/data changes, and Escape discard a gesture. None commits a move or placement. Placement occurs only after a stationary primary-pointer release, never at pointer-down. Middle/right-button navigation takes priority over placement. A second pointer transitions to pinch navigation without creating or moving objects, and ending a pinch cannot become a single-finger edit accidentally.

Object dragging renders a local tile-snapped preview. A successful release emits one coherent move, not one mutation per pointer event. Small pointer jitter does not move an object. Shift-deselecting an object cannot start a drag of the remaining selection.

A focused canvas supports `+`/`-`, Page Up/Page Down, Home, and Escape. Arrow keys pan in pan/read-only mode or with no selection; existing exact-coordinate selection nudging remains available in editing mode. Camera operations do not change plan objects.

## Rendering lifecycle

Redraw requests are coalesced into animation frames. The backing canvas is resized only when its pixel dimensions change. Unmounting disconnects resize observation, pointer capture, pending frames, and the window blur listener. The canvas width follows its actual container, including narrow layouts.

These lifecycle improvements are not evidence that spatial culling, required artwork, complete reference layers, or the entire renderer work package is finished. Those requirements remain in the delivery ledger.

## Verification

`tests/ReadModels/TerritoryPlanning/Frontend/TerritoryViewport.test.ts` covers nonzero-origin transforms, stable panning, cursor/pinch anchoring, bounds fitting, cancellation/wrong-pointer rejection, local previews, captured selections, tap-versus-drag discrimination, and additive selection semantics.

Browser interaction and visual acceptance remain required in addition to these pure-function regressions. Map-profile V2 and territory-document schema versions are distinct contracts; no compatibility reader is introduced by these changes.
