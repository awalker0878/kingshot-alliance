import type { MapLayerAvailability, MapSpatialDiagnostic, MapWorldRectangle } from './types';

export type TerritorySceneLayer =
  | 'terrain'
  | 'regions'
  | 'restrictions'
  | 'structures'
  | 'facilities'
  | 'resources'
  | 'coverage'
  | 'planned'
  | 'observed'
  | 'annotations'
  | 'validation';

export type TerritorySceneKind = 'factual' | 'planned' | 'observed' | 'annotation';

export type TerritorySceneEntity = {
  key: string;
  sourceKey: string;
  kind: TerritorySceneKind;
  layer: TerritorySceneLayer;
  label: string;
  bounds: MapWorldRectangle;
  /** Exact occupied terrain cells, encoded as [x, y, horizontal width]. */
  spans?: ReadonlyArray<readonly [number, number, number]>;
  assetKey: string | null;
  color: string | null;
  opacity: number;
  selectable: boolean;
  planObjectKey: string | null;
  confidence: string | null;
  provenance: string[];
  metadata: Record<string, unknown>;
};

export type TerritorySceneDocument = {
  schemaVersion: 1;
  mapId: string;
  mapChecksum: string;
  bounds: MapWorldRectangle;
  entities: TerritorySceneEntity[];
  layerAvailability: Record<string, MapLayerAvailability>;
  spatialDiagnostics: MapSpatialDiagnostic[];
};
