/** Presentation coordinates only. World coordinates remain south-west anchored and Y-up. */
export type Point = { x: number; y: number };
export type Viewport = Point & { zoom: number; width: number; height: number };
export type WorldBounds = Point & { width: number; height: number };

export const MIN_ZOOM = 0.06;
export const MAX_ZOOM = 8;
export const DRAG_THRESHOLD = 4;

export function worldPoint(point: Point, view: Viewport): Point {
  return {
    x: (point.x - view.width / 2) / view.zoom + view.x,
    y: (view.height / 2 - point.y) / view.zoom + view.y,
  };
}

export function screenPoint(point: Point, view: Viewport): Point {
  return {
    x: (point.x - view.x) * view.zoom + view.width / 2,
    y: view.height / 2 - (point.y - view.y) * view.zoom,
  };
}

export function fitBounds(bounds: WorldBounds, view: Viewport): Viewport {
  return {
    ...view,
    x: bounds.x + bounds.width / 2,
    y: bounds.y + bounds.height / 2,
    zoom: Math.min(
      MAX_ZOOM,
      Math.max(
        MIN_ZOOM,
        Math.min(view.width / Math.max(1, bounds.width), view.height / Math.max(1, bounds.height)) *
          0.92,
      ),
    ),
  };
}

/** Keep the same world point below an optional moving screen anchor (wheel or pinch). */
export function zoomAt(view: Viewport, factor: number, start: Point, end = start): Viewport {
  if (!Number.isFinite(factor) || factor <= 0) return view;
  const anchor = worldPoint(start, view);
  const zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, view.zoom * factor));
  return {
    ...view,
    zoom,
    x: anchor.x - (end.x - view.width / 2) / zoom,
    y: anchor.y + (end.y - view.height / 2) / zoom,
  };
}

/** Always derive panning from the gesture's original camera, never from a drifting anchor. */
export function panFrom(view: Viewport, start: Point, current: Point): Viewport {
  return {
    ...view,
    x: view.x - (current.x - start.x) / view.zoom,
    y: view.y + (current.y - start.y) / view.zoom,
  };
}

export function midpoint(a: Point, b: Point): Point {
  return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
}

export function distance(a: Point, b: Point): number {
  return Math.hypot(a.x - b.x, a.y - b.y);
}

export type PointerGesture = {
  kind: 'pan' | 'object' | 'box' | 'place';
  pointerId: number;
  start: Point;
  current: Point;
  view: Viewport;
  keys: string[];
  additive: boolean;
  moved: boolean;
};

export function advanceGesture(gesture: PointerGesture, current: Point): PointerGesture {
  return {
    ...gesture,
    current,
    moved: gesture.moved || distance(gesture.start, current) >= DRAG_THRESHOLD,
  };
}

export function gestureDelta(gesture: PointerGesture): Point {
  if (!gesture.moved) return { x: 0, y: 0 };
  return {
    x: Math.round((gesture.current.x - gesture.start.x) / gesture.view.zoom),
    y: Math.round((gesture.start.y - gesture.current.y) / gesture.view.zoom),
  };
}

export type GestureCompletion =
  | { kind: 'move'; keys: string[]; dx: number; dy: number }
  | { kind: 'place'; point: Point }
  | { kind: 'box'; start: Point; end: Point; additive: boolean }
  | null;

/** Cancellation has no completion path. A pointer may finish only the gesture it started. */
export function completeGesture(
  gesture: PointerGesture | null,
  pointerId: number,
  point: Point,
  cancelled: boolean,
): GestureCompletion {
  if (!gesture || cancelled || gesture.pointerId !== pointerId) return null;
  const current = advanceGesture(gesture, point);
  if (current.kind === 'object') {
    const delta = gestureDelta(current);
    return delta.x || delta.y
      ? { kind: 'move', keys: [...current.keys], dx: delta.x, dy: delta.y }
      : null;
  }
  if (current.kind === 'place' && !current.moved) {
    const world = worldPoint(point, current.view);
    return { kind: 'place', point: { x: Math.round(world.x), y: Math.round(world.y) } };
  }
  if (current.kind === 'box') {
    return { kind: 'box', start: current.start, end: point, additive: current.additive };
  }
  return null;
}
