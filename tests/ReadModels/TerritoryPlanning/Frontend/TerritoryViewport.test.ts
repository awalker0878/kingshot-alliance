import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  advanceGesture,
  completeGesture,
  fitBounds,
  gestureDelta,
  panFrom,
  screenPoint,
  worldPoint,
  zoomAt,
} from '../../../../resources/js/features/territory-planner/engine/viewport.ts';
import type {
  PointerGesture,
  Viewport,
} from '../../../../resources/js/features/territory-planner/engine/viewport.ts';

const view: Viewport = { x: 650, y: -300, zoom: 2, width: 900, height: 650 };
function gesture(kind: PointerGesture['kind'] = 'object'): PointerGesture {
  return {
    kind,
    pointerId: 7,
    start: { x: 100, y: 200 },
    current: { x: 100, y: 200 },
    view,
    keys: ['a', 'b'],
    additive: false,
    moved: false,
  };
}

test('world/screen transforms round trip nonzero origins and Y-up coordinates', () => {
  for (const point of [
    { x: 0, y: 0 },
    { x: 1100, y: -650 },
    { x: 123.25, y: 400.5 },
  ]) {
    assert.deepEqual(worldPoint(screenPoint(point, view), view), point);
  }
  assert.ok(screenPoint({ x: 650, y: -299 }, view).y < view.height / 2);
});

test('panning uses the original camera across every pointer sample', () => {
  const start = { x: 100, y: 100 };
  assert.equal(panFrom(view, start, { x: 120, y: 100 }).x, 640);
  assert.equal(panFrom(view, start, { x: 140, y: 100 }).x, 630);
  assert.equal(panFrom(view, start, { x: 160, y: 100 }).x, 620);
  assert.deepEqual(panFrom(view, start, start), view);
});

test('wheel and pinch keep the original world anchor under the moving midpoint', () => {
  const start = { x: 50, y: 60 };
  const end = { x: 250, y: 80 };
  const before = worldPoint(start, view);
  assert.deepEqual(worldPoint(start, zoomAt(view, 2, start)), before);
  assert.deepEqual(worldPoint(end, zoomAt(view, 200, start, end)), before);
  assert.equal(zoomAt(view, 200, start).zoom, 8);
  assert.equal(zoomAt(view, 0.0001, start).zoom, 0.06);
  assert.deepEqual(zoomAt(view, NaN, start), view);
});

test('fit uses exact bounds including negative and nonzero origins', () => {
  const fitted = fitBounds({ x: 500, y: -700, width: 100, height: 200 }, view);
  assert.equal(fitted.x, 550);
  assert.equal(fitted.y, -600);
  assert.equal(fitted.zoom, (650 / 200) * 0.92);
});

test('pointer cancellation and an unrelated pointer cannot finish a move or place', () => {
  for (const kind of ['object', 'place', 'box', 'pan'] as const) {
    assert.equal(completeGesture(gesture(kind), 7, { x: 130, y: 180 }, true), null);
    assert.equal(completeGesture(gesture(kind), 8, { x: 130, y: 180 }, false), null);
  }
  assert.equal(completeGesture(null, 7, { x: 130, y: 180 }, false), null);
});

test('drag preview remains local; completion uses captured keys and integer tile displacement', () => {
  const original = gesture();
  const preview = advanceGesture(original, { x: 131, y: 189 });
  assert.deepEqual(gestureDelta(preview), { x: 16, y: 6 });
  assert.deepEqual(original.current, original.start);
  assert.deepEqual(completeGesture(preview, 7, preview.current, false), {
    kind: 'move',
    keys: ['a', 'b'],
    dx: 16,
    dy: 6,
  });
});

test('small pointer jitter does not move an object and placement commits only a stationary tap', () => {
  assert.equal(completeGesture(gesture(), 7, { x: 101, y: 201 }, false), null);
  assert.deepEqual(completeGesture(gesture('place'), 7, { x: 100, y: 200 }, false), {
    kind: 'place',
    point: { x: 475, y: -237 },
  });
  const moved = advanceGesture(gesture('place'), { x: 140, y: 200 });
  assert.equal(completeGesture(moved, 7, moved.start, false), null);
});

test('box selection preserves its own additive modifier, not a later keyboard state', () => {
  const selected = { ...gesture('box'), additive: true };
  assert.deepEqual(completeGesture(selected, 7, { x: 300, y: 400 }, false), {
    kind: 'box',
    start: selected.start,
    end: { x: 300, y: 400 },
    additive: true,
  });
});
