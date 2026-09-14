import type { TerritorySceneDocument, TerritorySceneEntity } from './scene-types';
import type { WorldBounds } from './viewport.ts';

const CELL_SIZE = 32;
const indexes = new WeakMap<TerritorySceneDocument, SceneIndex>();

export function intersects(a: WorldBounds, b: WorldBounds): boolean {
  return a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y;
}

/** Reference geometry only. The index never establishes placement authority. */
class SceneIndex {
  private readonly buckets = new Map<string, number[]>();
  private readonly large: number[] = [];
  private readonly scene: TerritorySceneDocument;
  constructor(scene: TerritorySceneDocument) {
    this.scene = scene;
    scene.entities.forEach((entity, index) => {
      const [left, bottom, right, top] = cells(entity.bounds);
      if ((right - left + 1) * (top - bottom + 1) > 256) {
        this.large.push(index);
        return;
      }
      for (let y = bottom; y <= top; y++)
        for (let x = left; x <= right; x++) {
          const key = `${x}:${y}`;
          const bucket = this.buckets.get(key) ?? [];
          bucket.push(index);
          this.buckets.set(key, bucket);
        }
    });
  }
  query(bounds: WorldBounds): TerritorySceneEntity[] {
    const [left, bottom, right, top] = cells(bounds);
    // A far zoom can span more empty buckets than there are objects.
    if ((right - left + 1) * (top - bottom + 1) > 4096)
      return this.scene.entities.filter((entity) => intersects(entity.bounds, bounds));
    const matches = new Set(this.large);
    for (let y = bottom; y <= top; y++)
      for (let x = left; x <= right; x++) {
        for (const index of this.buckets.get(`${x}:${y}`) ?? []) matches.add(index);
      }
    return [...matches]
      .sort((a, b) => a - b)
      .map((index) => this.scene.entities[index]!)
      .filter((entity) => intersects(entity.bounds, bounds));
  }
}

function cells(bounds: WorldBounds): [number, number, number, number] {
  return [
    Math.floor(bounds.x / CELL_SIZE),
    Math.floor(bounds.y / CELL_SIZE),
    Math.floor((bounds.x + bounds.width) / CELL_SIZE),
    Math.floor((bounds.y + bounds.height) / CELL_SIZE),
  ];
}

export function sceneEntitiesInBounds(
  scene: TerritorySceneDocument,
  bounds: WorldBounds,
): TerritorySceneEntity[] {
  let index = indexes.get(scene);
  if (!index) {
    index = new SceneIndex(scene);
    indexes.set(scene, index);
  }
  return index.query(bounds);
}

export function sceneEntityContains(entity: TerritorySceneEntity, x: number, y: number): boolean {
  if (entity.spans)
    return entity.spans.some(
      ([left, bottom, width]) => x >= left && x < left + width && y >= bottom && y < bottom + 1,
    );
  return (
    x >= entity.bounds.x &&
    x < entity.bounds.x + entity.bounds.width &&
    y >= entity.bounds.y &&
    y < entity.bounds.y + entity.bounds.height
  );
}
