export type TerritoryObjectType = 'headquarters' | 'banner' | 'governor_city' | 'bear_trap';

export type PlanAlliance = {
  key: string;
  alliance_id: string | null;
  external_name: string | null;
  external_tag: string | null;
  display_name: string;
  presentation_color: string;
  sort_order: number;
  visible: boolean;
  locked: boolean;
};

export type PlanGroup = { key: string; label: string | null };

export type PlanObject = {
  key: string;
  alliance_key: string;
  group_key: string | null;
  type: TerritoryObjectType;
  player_id: string | null;
  external_player_name: string | null;
  label: string | null;
  x: number;
  y: number;
  rotation: number;
  sort_order: number;
  metadata: Record<string, unknown>;
};

export type MapRectangle = { width: number; height: number };
export type MapObjectVariant = {
  key: string;
  allowed_zones: string[];
  prerequisites: string[];
  confidence: string;
  provenance: string[];
};
export type MapObjectDefinition = {
  footprint: MapRectangle;
  coverage?: MapRectangle;
  max_per_alliance?: number;
  confidence?: string;
  provenance?: string[];
  variants?: MapObjectVariant[];
};
export type MapStructure = {
  key: string;
  name: string;
  category: string;
  x: number;
  y: number;
  footprint: MapRectangle;
  exclusion_tiles: number;
  city_exempt: boolean;
  blocks_placement?: boolean;
};
export type MapZone = {
  x: number;
  y: number;
  width: number;
  height: number;
  blocked_types: TerritoryObjectType[];
};
export type MapPlacementRule = {
  key: string;
  kind: 'blocking' | 'warning' | 'advisory' | 'fact';
  statement: string;
  parameters?: Record<string, unknown>;
  confidence: string;
  provenance: string[];
};
export type MapData = {
  id: string;
  schema_version: 2;
  release_status: 'released';
  released_at: string;
  observed_at: string;
  game_version: string | null;
  season: string | null;
  title: string;
  confidence: string;
  coordinate_system: { name: string; origin: string; tile_size: number };
  bounds: { x: number; y: number; width: number; height: number };
  object_types: Record<TerritoryObjectType, MapObjectDefinition>;
  zones: Record<string, MapZone>;
  structures: MapStructure[];
  placement_rules: MapPlacementRule[];
  facilities?: Array<{
    key: string;
    name: string;
    category: 'fortress' | 'sanctuary' | 'outpost';
    level?: number;
    x: number;
    y: number;
    confidence: string;
    provenance: string[];
  }>;
  resource_layers?: Record<string, unknown>;
};

export type ValidationIssue = { code: string; message: string; object_key?: string };
export type ValidationResult = {
  violations: ValidationIssue[];
  warnings: ValidationIssue[];
  suggestions: ValidationIssue[];
};
export type PlanningPreferences = {
  preferred_bear_radius_tiles?: number;
  march_seconds_per_tile?: number;
  selected_bear_trap_by_alliance?: Record<string, string>;
};

export type MarchAnalysis = {
  city_key: string;
  trap_key: string;
  distance_tiles: number;
  estimated_seconds: number | null;
};

export type AllianceAnalysis = {
  counts: Record<string, number>;
  governor_cities: number;
  covered_governor_cities: number;
  uncovered_governor_cities: number;
  coverage_percent: number | null;
  territory_components: number;
  territory_connected: boolean;
  banner_efficiency: number | null;
  violation_count: number;
  warning_count: number;
  suggestion_count: number;
  bear_distance_tiles: { average: number | null; median: number | null; max: number | null };
  estimated_march_seconds: {
    average: number | null;
    median: number | null;
    max: number | null;
  } | null;
  march_assumption_seconds_per_tile: number | null;
  selected_bear_trap_key: string | null;
  marches: MarchAnalysis[];
};
