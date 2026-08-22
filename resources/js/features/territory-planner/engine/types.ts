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

export type MapObjectDefinition = { size: number; coverage: number; max_per_alliance?: number };
export type MapStructure = {
  key: string;
  name: string;
  category: string;
  x: number;
  y: number;
  size: number;
  exclusion: number;
  city_exempt: boolean;
};
export type MapZone = {
  x: number;
  y: number;
  width: number;
  height: number;
  blocked_types: TerritoryObjectType[];
};
export type MapData = {
  id: string;
  schema_version: number;
  observed_at: string;
  source_label: string;
  source_uri: string | null;
  confidence: string;
  coordinate_system: { name: string; origin: string; tile_size: number };
  bounds: { x: number; y: number; width: number; height: number };
  object_types: Record<TerritoryObjectType, MapObjectDefinition>;
  zones: Record<string, MapZone>;
  structures: MapStructure[];
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
