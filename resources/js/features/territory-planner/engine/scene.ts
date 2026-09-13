import type { TerritorySceneDocument, TerritorySceneEntity } from './scene-types';
import type { MapData, MapStructure, PlanAlliance, PlanObject, TerritoryObjectType } from './types';

export type BuildTerritorySceneInput = {
  map: MapData;
  mapChecksum: string;
  alliances?: PlanAlliance[];
  objects?: PlanObject[];
};

function objectAssetKey(object: PlanObject): string {
  if (object.type === 'headquarters') {
    const variant = object.metadata.variant_key;
    return variant === 'plains_headquarters' ? 'headquarters.plains' : 'headquarters.badland';
  }
  if (object.type === 'governor_city') return 'governor_city.default';
  if (object.type === 'bear_trap') return 'bear_trap.default';
  return 'banner.default';
}

function structureAssetKey(structure: MapStructure): string | null {
  if (structure.category === 'castle') return 'castle.kings_castle';
  if (structure.category === 'turret') return 'turret.default';
  if (structure.category === 'fortress') return 'fortress.default';
  if (structure.category === 'sanctuary') return 'sanctuary.default';
  return null;
}

function facilityAssetKey(category: string, name: string): string | null {
  if (category === 'fortress') return 'fortress.default';
  if (category === 'sanctuary') return 'sanctuary.default';
  if (category !== 'outpost') return null;
  const slug = name
    .toLocaleLowerCase('en')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
  return slug ? `outpost.${slug}` : 'outpost.unknown';
}

function plannedBounds(map: MapData, object: PlanObject) {
  const definition = map.object_types[object.type];
  const rotated = object.rotation === 90 || object.rotation === 270;
  return {
    x: object.x,
    y: object.y,
    width: rotated ? definition.footprint.height : definition.footprint.width,
    height: rotated ? definition.footprint.width : definition.footprint.height,
  };
}

function coverageBounds(map: MapData, object: PlanObject) {
  const definition = map.object_types[object.type];
  const coverage = definition.coverage;
  if (!coverage) return null;
  const footprint = plannedBounds(map, object);
  const width =
    object.rotation === 90 || object.rotation === 270 ? coverage.height : coverage.width;
  const height =
    object.rotation === 90 || object.rotation === 270 ? coverage.width : coverage.height;
  return {
    x: object.x - Math.trunc((width - footprint.width) / 2),
    y: object.y - Math.trunc((height - footprint.height) / 2),
    width,
    height,
  };
}

/**
 * Canonical presentation projection. Factual map identity stays in GameWorld and
 * editable intent stays in TerritoryPlanning; this function only joins them for rendering/search/export.
 */
export function buildTerritoryScene(input: BuildTerritorySceneInput): TerritorySceneDocument {
  const { map, mapChecksum } = input;
  const alliances = input.alliances ?? [];
  const objects = input.objects ?? [];
  const colors = new Map(alliances.map((alliance) => [alliance.key, alliance.presentation_color]));
  const visible = new Set(
    alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key),
  );
  const entities: TerritorySceneEntity[] = [];

  for (const [key, zone] of Object.entries(map.zones)) {
    entities.push({
      key: `region:${key}`,
      sourceKey: key,
      kind: 'factual',
      layer: zone.blocked_types.length ? 'restrictions' : 'regions',
      label: key,
      bounds: { x: zone.x, y: zone.y, width: zone.width, height: zone.height },
      assetKey: null,
      color: null,
      opacity: 1,
      selectable: false,
      planObjectKey: null,
      confidence: map.confidence,
      provenance: [],
      metadata: { blocked_types: zone.blocked_types },
    });
  }

  for (const feature of map.terrain_features ?? []) {
    entities.push({
      key: `terrain:${feature.key}`,
      sourceKey: feature.key,
      kind: 'factual',
      layer: 'terrain',
      label: feature.family === 'lake' ? 'Lake' : 'Mountain',
      bounds: feature.bounds,
      assetKey: `terrain.${feature.family}`,
      color: null,
      opacity: 1,
      selectable: true,
      planObjectKey: null,
      confidence: map.layer_availability?.terrain.confidence ?? map.confidence,
      provenance: [],
      metadata: { family: feature.family, cell_count: feature.cell_count },
    });
  }

  for (const resource of map.resource_nodes ?? []) {
    entities.push({
      key: `resource:${resource.key}`,
      sourceKey: resource.key,
      kind: 'factual',
      layer: 'resources',
      label: resource.resource_type,
      bounds: { x: resource.x, y: resource.y, ...resource.footprint },
      assetKey: `resource.${resource.resource_type}`,
      color: null,
      opacity: 1,
      selectable: true,
      planObjectKey: null,
      confidence: map.layer_availability?.resources.confidence ?? map.confidence,
      provenance: [],
      metadata: { resource_type: resource.resource_type },
    });
  }

  const matchingFacility = new Map<string, NonNullable<MapData['facilities']>[number]>();
  for (const facility of map.facilities ?? []) {
    if (facility.category === 'fortress' || facility.category === 'sanctuary')
      matchingFacility.set(`${facility.category}:${facility.x}:${facility.y}`, facility);
  }
  const consumedFacilities = new Set<string>();
  for (const structure of map.structures) {
    const facility = matchingFacility.get(`${structure.category}:${structure.x}:${structure.y}`);
    if (facility) consumedFacilities.add(facility.key);
    entities.push({
      key: `structure:${structure.key}`,
      sourceKey: structure.key,
      kind: 'factual',
      layer: 'structures',
      label: facility?.name ?? structure.name,
      bounds: { x: structure.x, y: structure.y, ...structure.footprint },
      assetKey: structureAssetKey(structure),
      color: null,
      opacity: 1,
      selectable: true,
      planObjectKey: null,
      confidence: facility?.confidence ?? null,
      provenance: [...new Set([...(structure.provenance ?? []), ...(facility?.provenance ?? [])])],
      metadata: {
        category: structure.category,
        exclusion_tiles: structure.exclusion_tiles,
        city_exempt: structure.city_exempt,
        facility_key: facility?.key ?? null,
        facility_level: facility?.level ?? null,
      },
    });
  }

  for (const facility of map.facilities ?? []) {
    if (consumedFacilities.has(facility.key)) continue;
    // Unknown Outpost footprints remain reference markers. A 1×1 display marker is
    // deliberately not fed back into placement validation.
    entities.push({
      key: `facility:${facility.key}`,
      sourceKey: facility.key,
      kind: 'factual',
      layer: 'facilities',
      label: facility.name,
      bounds: { x: facility.x, y: facility.y, width: 1, height: 1 },
      assetKey: facilityAssetKey(facility.category, facility.name),
      color: null,
      opacity: 1,
      selectable: true,
      planObjectKey: null,
      confidence: facility.confidence,
      provenance: facility.provenance,
      metadata: {
        category: facility.category,
        level: facility.level ?? null,
        reference_marker: true,
      },
    });
  }

  for (const object of objects) {
    if (alliances.length && !visible.has(object.alliance_key)) continue;
    const color = colors.get(object.alliance_key) ?? '#4da3ff';
    const coverage = coverageBounds(map, object);
    if (coverage) {
      entities.push({
        key: `coverage:${object.key}`,
        sourceKey: object.key,
        kind: 'planned',
        layer: 'coverage',
        label: object.label ?? object.type,
        bounds: coverage,
        assetKey: null,
        color,
        opacity: 0.12,
        selectable: false,
        planObjectKey: object.key,
        confidence: null,
        provenance: [],
        metadata: { object_type: object.type },
      });
    }
    entities.push({
      key: `planned:${object.key}`,
      sourceKey: object.key,
      kind: 'planned',
      layer: 'planned',
      label: object.label ?? object.external_player_name ?? object.type,
      bounds: plannedBounds(map, object),
      assetKey: objectAssetKey(object),
      color,
      opacity: 1,
      selectable: true,
      planObjectKey: object.key,
      confidence: null,
      provenance: [],
      metadata: {
        object_type: object.type,
        rotation: object.rotation,
        alliance_key: object.alliance_key,
      },
    });
  }

  return {
    schemaVersion: 1,
    mapId: map.id,
    mapChecksum,
    bounds: map.bounds,
    entities,
    layerAvailability: map.layer_availability ?? {},
    spatialDiagnostics: map.spatial_diagnostics ?? [],
  };
}

export function sceneEntitiesForQuery(
  scene: TerritorySceneDocument,
  query: string,
  limit = 200,
): TerritorySceneEntity[] {
  const normalized = query.trim().toLocaleLowerCase();
  const result: TerritorySceneEntity[] = [];
  for (const entity of scene.entities) {
    if (!entity.selectable) continue;
    if (
      normalized &&
      ![entity.label, entity.sourceKey, entity.layer, entity.assetKey ?? '']
        .join(' ')
        .toLocaleLowerCase()
        .includes(normalized)
    )
      continue;
    result.push(entity);
    if (result.length === limit) break;
  }
  return result;
}

export function sceneEntityForPlannedObject(
  scene: TerritorySceneDocument,
  objectKey: string,
): TerritorySceneEntity | null {
  return (
    scene.entities.find(
      (entity) => entity.layer === 'planned' && entity.planObjectKey === objectKey,
    ) ?? null
  );
}

export function rotatedFootprint(
  map: MapData,
  type: TerritoryObjectType,
  rotation: number,
): { width: number; height: number } {
  const footprint = map.object_types[type].footprint;
  return rotation === 90 || rotation === 270
    ? { width: footprint.height, height: footprint.width }
    : footprint;
}
