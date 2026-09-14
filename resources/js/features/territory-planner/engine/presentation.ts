import { intersects, sceneEntitiesInBounds } from './scene-index.ts';
import type {
  TerritorySceneDocument,
  TerritorySceneEntity,
  TerritorySceneLayer,
} from './scene-types';
import { screenPoint } from './viewport.ts';
import type { Point, Viewport, WorldBounds } from './viewport.ts';

type Paint = {
  fill: string;
  stroke?: string;
  strokeWidth?: number;
  dash?: number[];
  opacity: number;
};
export type DrawingCommand =
  | ({ kind: 'rect'; key: string; bounds: WorldBounds } & Paint)
  | ({ kind: 'path'; key: string; rectangles: WorldBounds[] } & Paint)
  | {
      kind: 'text';
      key: string;
      x: number;
      y: number;
      text: string;
      size: number;
      fill: string;
      opacity: number;
    }
  | { kind: 'image'; key: string; bounds: WorldBounds; href: string; opacity: number };

export type PresentationImage = {
  href: string;
  width: number;
  height: number;
  anchor: Point;
  worldWidth: number;
};
export type PresentationOptions = {
  layers?: Partial<Record<TerritorySceneLayer, { visible: boolean; opacity: number }>>;
  selectedKeys?: readonly string[];
  showGrid?: boolean;
  showLabels?: boolean;
  preview?: { keys: readonly string[]; delta: Point };
  image?: (assetKey: string, pixels: number) => PresentationImage | null;
};

const layerOrder: TerritorySceneLayer[] = [
  'regions',
  'terrain',
  'restrictions',
  'resources',
  'coverage',
  'structures',
  'facilities',
  'planned',
  'observed',
  'annotations',
  'validation',
];
const selectedColor = '#fff4b8';

export function layerIsVisible(layer: TerritorySceneLayer, options: PresentationOptions): boolean {
  return options.layers?.[layer]?.visible !== false && (options.layers?.[layer]?.opacity ?? 1) > 0;
}

export function screenBounds(bounds: WorldBounds, view: Viewport): WorldBounds {
  const point = screenPoint({ x: bounds.x, y: bounds.y + bounds.height }, view);
  return { ...point, width: bounds.width * view.zoom, height: bounds.height * view.zoom };
}

/** One ordered, bounded display list drives the interactive Canvas and visual exports. */
export function buildPresentation(
  scene: TerritorySceneDocument,
  view: Viewport,
  options: PresentationOptions = {},
): DrawingCommand[] {
  const margin = 128 / view.zoom;
  const worldView = {
    x: view.x - view.width / view.zoom / 2 - margin,
    y: view.y - view.height / view.zoom / 2 - margin,
    width: view.width / view.zoom + 2 * margin,
    height: view.height / view.zoom + 2 * margin,
  };
  const screen = { x: -128, y: -128, width: view.width + 256, height: view.height + 256 };
  const selected = new Set(options.selectedKeys ?? []);
  const moved = new Set(options.preview?.keys ?? []);
  const candidates = sceneEntitiesInBounds(scene, worldView);
  if (moved.size) {
    const known = new Set(candidates.map((entity) => entity.key));
    for (const entity of scene.entities)
      if (entity.planObjectKey && moved.has(entity.planObjectKey) && !known.has(entity.key))
        candidates.push(entity);
  }
  candidates.sort((a, b) => layerOrder.indexOf(a.layer) - layerOrder.indexOf(b.layer));
  const commands: DrawingCommand[] = [];
  const labels: { entity: TerritorySceneEntity; bounds: WorldBounds; selected: boolean }[] = [];
  for (const entity of candidates) {
    if (!layerIsVisible(entity.layer, options)) continue;
    const delta =
      entity.planObjectKey && moved.has(entity.planObjectKey)
        ? options.preview!.delta
        : { x: 0, y: 0 };
    const bounds = screenBounds(
      { ...entity.bounds, x: entity.bounds.x + delta.x, y: entity.bounds.y + delta.y },
      view,
    );
    if (!intersects(bounds, screen)) continue;
    const opacity =
      entity.opacity * Math.max(0, Math.min(1, options.layers?.[entity.layer]?.opacity ?? 1));
    const isSelected = selected.has(entity.planObjectKey ?? entity.key);
    const region = entity.layer === 'regions' || entity.layer === 'restrictions';
    const paint: Paint = {
      fill: entity.color ?? colorFor(entity),
      opacity,
      ...(region
        ? {
            fill: 'none',
            stroke: entity.layer === 'restrictions' ? '#b9725c' : '#5b594a',
            strokeWidth: 1,
          }
        : {}),
      ...(entity.layer === 'restrictions' || entity.kind === 'observed'
        ? { stroke: '#f3d36a', dash: [4, 3], strokeWidth: 1 }
        : {}),
      ...(entity.layer === 'planned' ? { stroke: '#b8c8d4', strokeWidth: 1 } : {}),
      ...(isSelected ? { stroke: selectedColor, strokeWidth: 2 } : {}),
    };
    if (entity.spans) {
      // The enclosing rectangle is only an index bound. Never fill its empty cells.
      const rectangles = entity.spans
        .map(([x, y, width]) => screenBounds({ x, y, width, height: 1 }, view))
        .filter((rectangle) => intersects(rectangle, screen));
      if (rectangles.length) commands.push({ kind: 'path', key: entity.key, rectangles, ...paint });
    } else {
      commands.push({ kind: 'rect', key: entity.key, bounds, ...paint });
      const art = entity.assetKey
        ? options.image?.(entity.assetKey, Math.max(bounds.width, bounds.height))
        : null;
      if (art) {
        const scale = Math.min(
          128 / art.width,
          128 / art.height,
          Math.max(8, art.worldWidth * view.zoom) / art.width,
        );
        const width = art.width * scale;
        const height = art.height * scale;
        commands.push({
          kind: 'image',
          key: entity.key,
          opacity,
          bounds: {
            x: bounds.x + bounds.width / 2 - width * art.anchor.x,
            y: bounds.y + bounds.height - height * art.anchor.y,
            width,
            height,
          },
          href: art.href,
        });
      } else if (entity.assetKey && view.zoom >= 2 && bounds.width >= 10 && bounds.height >= 10) {
        commands.push({
          kind: 'text',
          key: entity.key + ':symbol',
          x: bounds.x + 2,
          y: bounds.y + Math.min(12, bounds.height),
          text: fallbackSymbol(entity.assetKey),
          size: 10,
          fill: '#ffffff',
          opacity,
        });
      }
    }
    if (
      entity.selectable &&
      (isSelected || (options.showLabels !== false && view.zoom >= 1.4 && !entity.spans))
    )
      labels.push({ entity, bounds, selected: isSelected });
  }
  if (options.showGrid) addGrid(commands, scene.bounds, view);
  const occupied: WorldBounds[] = [];
  labels.sort((a, b) => Number(b.selected) - Number(a.selected));
  let ordinaryLabels = 0;
  for (const { entity, bounds, selected: isSelected } of labels) {
    const text = [...entity.label].slice(0, isSelected ? 160 : 120).join('');
    const label = {
      x: bounds.x + 2,
      y: bounds.y - 17,
      width: Math.min(720, [...text].length * 6.5 + 4),
      height: 16,
    };
    if (
      !isSelected &&
      (ordinaryLabels >= 300 || occupied.some((other) => intersects(other, label)))
    )
      continue;
    if (!isSelected) ordinaryLabels++;
    occupied.push(label);
    commands.push({
      kind: 'text',
      key: entity.key + ':label',
      x: label.x,
      y: label.y + 12,
      text,
      size: 11,
      fill: isSelected ? selectedColor : '#eef4f8',
      opacity: 1,
    });
  }
  return commands;
}

function colorFor(entity: TerritorySceneEntity): string {
  if (entity.layer === 'terrain') return entity.assetKey === 'terrain.lake' ? '#335f78' : '#485564';
  if (entity.layer === 'resources') return '#c49a58';
  if (entity.layer === 'facilities') return '#b39a72';
  return '#8b7d6b';
}

function fallbackSymbol(key: string): string {
  const family = key.split('.')[0];
  return (
    (
      {
        headquarters: 'HQ',
        banner: '⚑',
        governor_city: 'G',
        bear_trap: 'B',
        castle: 'C',
        turret: 'T',
        fortress: 'F',
        sanctuary: 'S',
        outpost: 'O',
        resource: 'R',
      } as Record<string, string>
    )[family ?? ''] ?? '?'
  );
}

function addGrid(commands: DrawingCommand[], bounds: WorldBounds, view: Viewport): void {
  const step = Math.max(1, 10 ** Math.ceil(Math.log10(50 / view.zoom)));
  const left = Math.max(bounds.x, view.x - view.width / view.zoom / 2);
  const right = Math.min(bounds.x + bounds.width, view.x + view.width / view.zoom / 2);
  const bottom = Math.max(bounds.y, view.y - view.height / view.zoom / 2);
  const top = Math.min(bounds.y + bounds.height, view.y + view.height / view.zoom / 2);
  for (let x = Math.ceil(left / step) * step; x <= right; x += step) {
    const a = screenPoint({ x, y: top }, view),
      b = screenPoint({ x, y: bottom }, view);
    commands.push({
      kind: 'rect',
      key: `grid:x:${x}`,
      bounds: { x: a.x, y: a.y, width: 0.5, height: b.y - a.y },
      fill: '#526271',
      opacity: 0.35,
    });
    commands.push({
      kind: 'text',
      key: `coordinate:x:${x}`,
      x: a.x + 3,
      y: Math.max(12, a.y + 12),
      text: String(x),
      size: 10,
      fill: '#aab7c3',
      opacity: 1,
    });
  }
  for (let y = Math.ceil(bottom / step) * step; y <= top; y += step) {
    const a = screenPoint({ x: left, y }, view),
      b = screenPoint({ x: right, y }, view);
    commands.push({
      kind: 'rect',
      key: `grid:y:${y}`,
      bounds: { x: a.x, y: a.y, width: b.x - a.x, height: 0.5 },
      fill: '#526271',
      opacity: 0.35,
    });
    commands.push({
      kind: 'text',
      key: `coordinate:y:${y}`,
      x: Math.max(2, a.x + 2),
      y: a.y - 3,
      text: String(y),
      size: 10,
      fill: '#aab7c3',
      opacity: 1,
    });
  }
}

export function paintPresentation(
  context: CanvasRenderingContext2D,
  commands: DrawingCommand[],
  images?: (href: string) => CanvasImageSource | null,
): void {
  for (const command of commands) {
    context.globalAlpha = command.opacity;
    if (command.kind === 'text') {
      context.fillStyle = command.fill;
      context.font = `${command.size}px sans-serif`;
      context.fillText(command.text, command.x, command.y);
    } else if (command.kind === 'image') {
      const image = images?.(command.href);
      if (image)
        context.drawImage(
          image,
          command.bounds.x,
          command.bounds.y,
          command.bounds.width,
          command.bounds.height,
        );
    } else {
      context.beginPath();
      const rectangles = command.kind === 'rect' ? [command.bounds] : command.rectangles;
      for (const rect of rectangles) context.rect(rect.x, rect.y, rect.width, rect.height);
      if (command.fill !== 'none') {
        context.fillStyle = command.fill;
        context.fill();
      }
      if (command.stroke) {
        context.strokeStyle = command.stroke;
        context.lineWidth = command.strokeWidth ?? 1;
        context.setLineDash(command.dash ?? []);
        context.stroke();
      }
    }
  }
  context.globalAlpha = 1;
  context.setLineDash([]);
}
