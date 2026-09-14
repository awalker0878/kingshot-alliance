import { spatialCollisionCodes } from './spatial.ts';
import type {
  AllianceAnalysis,
  MapData,
  PlanObject,
  PlanningPreferences,
  ValidationIssue,
  ValidationResult,
} from './types';

type Rect = { x: number; y: number; width: number; height: number };
type CoverageSource = { key: string; type: PlanObject['type']; rect: Rect };

export function rectFor(object: PlanObject, map: MapData): Rect | null {
  const definition = map.object_types[object.type];
  if (!definition || definition.footprint.width < 1 || definition.footprint.height < 1) return null;
  const swap = object.rotation === 90 || object.rotation === 270;
  return {
    x: object.x,
    y: object.y,
    width: swap ? definition.footprint.height : definition.footprint.width,
    height: swap ? definition.footprint.width : definition.footprint.height,
  };
}

export function coverageRect(object: PlanObject, map: MapData): Rect | null {
  const definition = map.object_types[object.type];
  if (!definition?.coverage) return null;
  const swap = object.rotation === 90 || object.rotation === 270;
  const width = swap ? definition.coverage.height : definition.coverage.width;
  const height = swap ? definition.coverage.width : definition.coverage.height;
  const footprint = rectFor(object, map);
  if (!footprint) return null;
  const offsetX = Math.trunc((width - footprint.width) / 2);
  const offsetY = Math.trunc((height - footprint.height) / 2);
  return {
    x: object.x - offsetX,
    y: object.y - offsetY,
    width,
    height,
  };
}

function intersects(a: Rect, b: Rect): boolean {
  return a.x < b.x + b.width && a.x + a.width > b.x && a.y < b.y + b.height && a.y + a.height > b.y;
}

function touchesOrIntersects(a: Rect, b: Rect): boolean {
  return (
    a.x <= b.x + b.width && a.x + a.width >= b.x && a.y <= b.y + b.height && a.y + a.height >= b.y
  );
}

function inside(rect: Rect, bounds: Rect): boolean {
  return (
    rect.x >= bounds.x &&
    rect.y >= bounds.y &&
    rect.x + rect.width <= bounds.x + bounds.width &&
    rect.y + rect.height <= bounds.y + bounds.height
  );
}

function containsCell(rect: Rect, x: number, y: number): boolean {
  return (
    x >= rect.x && x + 1 <= rect.x + rect.width && y >= rect.y && y + 1 <= rect.y + rect.height
  );
}

export function coveredRatio(target: Rect, territory: Rect[]): number {
  const area = target.width * target.height;
  if (area < 1) return 0;
  let covered = 0;
  for (let x = target.x; x < target.x + target.width; x += 1) {
    for (let y = target.y; y < target.y + target.height; y += 1) {
      if (territory.some((coverage) => containsCell(coverage, x, y))) covered += 1;
    }
  }
  return covered / area;
}

/** Exact union area; complexity depends on source count, never world cell count. */
export function unionArea(rectangles: Rect[]): number {
  const edges = [...new Set(rectangles.flatMap((rect) => [rect.x, rect.x + rect.width]))].sort(
    (a, b) => a - b,
  );
  let area = 0;
  for (let index = 1; index < edges.length; index += 1) {
    const start = edges[index - 1];
    const end = edges[index];
    if (start === undefined || end === undefined) continue;
    const intervals = rectangles
      .filter((rect) => rect.x < end && rect.x + rect.width > start)
      .map((rect) => [rect.y, rect.y + rect.height] as const)
      .sort((a, b) => a[0] - b[0]);
    let previous: number | null = null;
    let length = 0;
    for (const [from, to] of intervals) {
      length += Math.max(0, to - Math.max(from, previous ?? from));
      previous = Math.max(previous ?? to, to);
    }
    area += (end - start) * length;
  }
  return area;
}

export function allianceResourceMinimumRatio(map: MapData): number {
  const rule = map.placement_rules.find(
    (candidate) => candidate.key === 'alliance_resource_territory_ratio',
  );
  const ratio = rule?.parameters?.minimum_covered_ratio;
  if (typeof ratio !== 'number' || ratio < 0 || ratio > 1) {
    throw new Error('Selected Kingdom map release has no valid Alliance resource territory ratio.');
  }
  return ratio;
}

function issue(code: string, message: string, objectKey?: string): ValidationIssue {
  return objectKey ? { code, message, object_key: objectKey } : { code, message };
}

function unique(issues: ValidationIssue[]): ValidationIssue[] {
  const seen = new Set<string>();
  return issues.filter((item) => {
    const key = `${item.code}|${item.object_key ?? ''}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

function coverageSources(map: MapData, objects: PlanObject[]): CoverageSource[] {
  return objects.flatMap((object) => {
    const rect = coverageRect(object, map);
    return rect ? [{ key: object.key, type: object.type, rect }] : [];
  });
}

function coverageComponents(rectangles: Rect[]): number[][] {
  const visited = new Set<number>();
  const components: number[][] = [];
  rectangles.forEach((_rectangle, start) => {
    if (visited.has(start)) return;
    const component: number[] = [];
    const queue = [start];
    while (queue.length) {
      const index = queue.pop();
      if (index === undefined || visited.has(index)) continue;
      visited.add(index);
      component.push(index);
      const current = rectangles[index];
      if (!current) continue;
      rectangles.forEach((candidate, candidateIndex) => {
        if (visited.has(candidateIndex) || candidateIndex === index) return;
        if (touchesOrIntersects(current, candidate)) queue.push(candidateIndex);
      });
    }
    component.sort((a, b) => a - b);
    components.push(component);
  });
  return components;
}

function selectedTrap(
  allianceKey: string,
  traps: PlanObject[],
  preferences: PlanningPreferences,
): PlanObject | null {
  const selectedKey = preferences.selected_bear_trap_by_alliance?.[allianceKey];
  if (!selectedKey) return null;
  return traps.find((trap) => trap.key === selectedKey) ?? null;
}

function targetTrapForCity(
  city: PlanObject,
  traps: PlanObject[],
  preferences: PlanningPreferences,
): { trap: PlanObject; distance: number } | null {
  if (!traps.length) return null;
  const selected = selectedTrap(city.alliance_key, traps, preferences);
  if (selected) {
    return { trap: selected, distance: Math.hypot(city.x - selected.x, city.y - selected.y) };
  }
  return traps.reduce<{ trap: PlanObject; distance: number } | null>((best, trap) => {
    const distance = Math.hypot(city.x - trap.x, city.y - trap.y);
    return best === null || distance < best.distance ? { trap, distance } : best;
  }, null);
}

export function validatePlacement(
  map: MapData,
  objects: PlanObject[],
  preferences: PlanningPreferences,
): ValidationResult {
  const violations: ValidationIssue[] = [];
  const warnings: ValidationIssue[] = [];
  const suggestions: ValidationIssue[] = [];
  const rectangles = new Map<string, Rect>();
  const countsByAlliance = new Map<string, number>();
  const bounds = map.bounds;

  for (const object of objects) {
    if (![0, 90, 180, 270].includes(object.rotation ?? 0)) {
      violations.push(
        issue('invalid_rotation', 'Rotation must be 0, 90, 180, or 270 degrees.', object.key),
      );
      continue;
    }
    const rect = rectFor(object, map);
    if (!rect) {
      violations.push(
        issue(
          'unknown_object_type',
          'This object type is not supported by the selected map dataset.',
          object.key,
        ),
      );
      continue;
    }
    const definition = map.object_types[object.type];
    const countKey = `${object.alliance_key}|${object.type}`;
    const count = (countsByAlliance.get(countKey) ?? 0) + 1;
    countsByAlliance.set(countKey, count);
    if (definition.max_per_alliance && count > definition.max_per_alliance) {
      violations.push(
        issue(
          'alliance_object_cap',
          'This Alliance exceeds the selected map dataset object cap.',
          object.key,
        ),
      );
    }
    rectangles.set(object.key, rect);
    if (!inside(rect, bounds)) {
      violations.push(
        issue('map_bounds', 'The object footprint must stay inside the Kingdom map.', object.key),
      );
      continue;
    }

    for (const code of spatialCollisionCodes(map, rect)) {
      violations.push(
        issue(
          code,
          code === 'terrain_collision'
            ? 'The object overlaps a materialized lake or mountain cell.'
            : 'The object overlaps a materialized resource footprint.',
          object.key,
        ),
      );
    }

    for (const structure of map.structures) {
      if (structure.blocks_placement === false) continue;
      const actual = {
        x: structure.x,
        y: structure.y,
        width: structure.footprint.width,
        height: structure.footprint.height,
      };
      if (intersects(rect, actual)) {
        violations.push(
          issue(
            'structure_collision',
            'The object overlaps a fixed Kingdom structure.',
            object.key,
          ),
        );
        break;
      }
      const exclusion = Math.max(structure.exclusion_tiles, 0);
      if (exclusion === 0) continue;
      const forbidden = {
        x: structure.x - exclusion,
        y: structure.y - exclusion,
        width: structure.footprint.width + exclusion * 2,
        height: structure.footprint.height + exclusion * 2,
      };
      if (
        intersects(rect, forbidden) &&
        !(object.type === 'governor_city' && structure.city_exempt)
      ) {
        violations.push(
          issue(
            'structure_exclusion',
            'The object overlaps a fixed structure no-build zone.',
            object.key,
          ),
        );
        break;
      }
    }

    for (const zone of Object.values(map.zones)) {
      if (intersects(rect, zone) && zone.blocked_types.includes(object.type)) {
        violations.push(
          issue('zone_restriction', 'The object type is not allowed in this map zone.', object.key),
        );
      }
    }
  }

  const entries = [...rectangles.entries()];
  entries.forEach(([, rect], index) => {
    for (let other = index + 1; other < entries.length; other += 1) {
      const candidate = entries[other];
      if (candidate && intersects(rect, candidate[1])) {
        violations.push(
          issue('object_collision', 'Planned object footprints cannot overlap.', candidate[0]),
        );
      }
    }
  });

  const radius = preferences.preferred_bear_radius_tiles;
  if (radius && radius > 0) {
    const trapsByAlliance = new Map<string, PlanObject[]>();
    objects
      .filter((object) => object.type === 'bear_trap')
      .forEach((trap) =>
        trapsByAlliance.set(trap.alliance_key, [
          ...(trapsByAlliance.get(trap.alliance_key) ?? []),
          trap,
        ]),
      );
    objects
      .filter((object) => object.type === 'governor_city')
      .forEach((city) => {
        const target = targetTrapForCity(
          city,
          trapsByAlliance.get(city.alliance_key) ?? [],
          preferences,
        );
        if (target && target.distance > radius) {
          warnings.push(
            issue(
              'preferred_bear_radius',
              'This Governor city is outside the plan preferred Bear Trap radius.',
              city.key,
            ),
          );
        }
      });
  }

  const violatingObjectKeys = new Set(
    violations.flatMap((violation) => (violation.object_key ? [violation.object_key] : [])),
  );
  const allianceObjects = new Map<string, PlanObject[]>();
  objects.forEach((object) =>
    allianceObjects.set(object.alliance_key, [
      ...(allianceObjects.get(object.alliance_key) ?? []),
      object,
    ]),
  );
  for (const scopedObjects of allianceObjects.values()) {
    if (scopedObjects.some((object) => violatingObjectKeys.has(object.key))) continue;

    const sources = coverageSources(map, scopedObjects);
    const components = coverageComponents(sources.map((source) => source.rect));
    if (components.length > 1 && sources.length) {
      warnings.push(
        issue(
          'disconnected_territory',
          'Alliance territory coverage is split into disconnected regions.',
          sources[0]?.key,
        ),
      );
    }
    for (const component of components) {
      const members = component.flatMap((index) =>
        sources[index] ? [sources[index] as CoverageSource] : [],
      );
      const hasHeadquarters = members.some((source) => source.type === 'headquarters');
      if (!hasHeadquarters) {
        members
          .filter((source) => source.type === 'banner')
          .forEach((source) =>
            violations.push(
              issue(
                'banner_hq_connectivity',
                'Alliance Banners must remain connected to an Alliance Headquarters.',
                source.key,
              ),
            ),
          );
      }
    }

    const firstCity = scopedObjects.find((object) => object.type === 'governor_city');
    if (!firstCity) continue;
    if (!scopedObjects.some((object) => object.type === 'headquarters')) {
      suggestions.push(
        issue(
          'consider_headquarters',
          'Consider placing the Alliance HQ before finalizing this layout.',
          firstCity.key,
        ),
      );
    }
    if (!scopedObjects.some((object) => object.type === 'banner')) {
      suggestions.push(
        issue(
          'consider_banner_coverage',
          'Consider adding Alliance Banners to establish territory coverage for Governor cities.',
          firstCity.key,
        ),
      );
    }
    if (!scopedObjects.some((object) => object.type === 'bear_trap')) {
      suggestions.push(
        issue(
          'consider_bear_trap',
          'Consider placing a Bear Trap to analyze hive march distances.',
          firstCity.key,
        ),
      );
    }
  }

  return {
    violations: unique(violations),
    warnings: unique(warnings),
    suggestions: unique(suggestions),
  };
}

function stats(values: number[]) {
  if (!values.length) return { average: null, median: null, max: null };
  const sorted = [...values].sort((a, b) => a - b);
  const middle = Math.floor(sorted.length / 2);
  const median =
    sorted.length % 2 === 0
      ? ((sorted[middle - 1] ?? 0) + (sorted[middle] ?? 0)) / 2
      : (sorted[middle] ?? 0);
  return {
    average:
      Math.round((values.reduce((sum, value) => sum + value, 0) / values.length) * 100) / 100,
    median: Math.round(median * 100) / 100,
    max: Math.round(Math.max(...values) * 100) / 100,
  };
}

function qualityCounts(
  validation: ValidationResult,
  objects: PlanObject[],
): Record<string, { violations: number; warnings: number; suggestions: number }> {
  const allianceByObject = new Map(objects.map((object) => [object.key, object.alliance_key]));
  const result: Record<string, { violations: number; warnings: number; suggestions: number }> = {};
  const count = (issues: ValidationIssue[], field: 'violations' | 'warnings' | 'suggestions') => {
    issues.forEach((item) => {
      if (!item.object_key) return;
      const allianceKey = allianceByObject.get(item.object_key);
      if (!allianceKey) return;
      const current = result[allianceKey] ?? { violations: 0, warnings: 0, suggestions: 0 };
      current[field] += 1;
      result[allianceKey] = current;
    });
  };
  count(validation.violations, 'violations');
  count(validation.warnings, 'warnings');
  count(validation.suggestions, 'suggestions');
  return result;
}

export function analyzeLayout(
  map: MapData,
  objects: PlanObject[],
  preferences: PlanningPreferences,
): Record<string, AllianceAnalysis> {
  const groups = new Map<string, PlanObject[]>();
  objects.forEach((object) =>
    groups.set(object.alliance_key, [...(groups.get(object.alliance_key) ?? []), object]),
  );
  const validation = validatePlacement(map, objects, preferences);
  const quality = qualityCounts(validation, objects);
  const result: Record<string, AllianceAnalysis> = {};

  for (const [allianceKey, allianceGroup] of groups) {
    const counts: Record<string, number> = {};
    allianceGroup.forEach((object) => {
      counts[object.type] = (counts[object.type] ?? 0) + 1;
    });
    const cities = allianceGroup.filter((object) => object.type === 'governor_city');
    const traps = allianceGroup.filter((object) => object.type === 'bear_trap');
    const sources = coverageSources(map, allianceGroup);
    const territory = sources.map((source) => source.rect);
    const covered = cities.filter((city) => {
      const target = rectFor(city, map);
      return target !== null && coveredRatio(target, territory) >= 1 - 1e-9;
    }).length;

    const members = coverageComponents(territory);
    const components = members.length;
    const anchored = members.filter((indices) =>
      indices.some((index) => sources[index]?.type === 'headquarters'),
    ).length;
    const territoryArea = unionArea(territory);
    const hqTerritory = sources
      .filter((source) => source.type === 'headquarters')
      .map((source) => source.rect);
    const redundantBanners = sources.filter(
      (source, index) =>
        source.type === 'banner' &&
        coveredRatio(
          source.rect,
          territory.filter((_rect, other) => index !== other),
        ) >=
          1 - 1e-9,
    ).length;
    const cityRects = cities.flatMap((city) => {
      const rect = rectFor(city, map);
      return rect ? [rect] : [];
    });
    const densityArea = cityRects.length
      ? (Math.max(...cityRects.map((rect) => rect.x + rect.width)) -
          Math.min(...cityRects.map((rect) => rect.x))) *
        (Math.max(...cityRects.map((rect) => rect.y + rect.height)) -
          Math.min(...cityRects.map((rect) => rect.y)))
      : null;
    const seconds = preferences.march_seconds_per_tile;
    const marches = cities.flatMap((city) => {
      const target = targetTrapForCity(city, traps, preferences);
      if (!target) return [];
      return [
        {
          city_key: city.key,
          trap_key: target.trap.key,
          distance_tiles: Math.round(target.distance * 100) / 100,
          estimated_seconds:
            seconds === undefined ? null : Math.round(target.distance * seconds * 100) / 100,
        },
      ];
    });
    const distances = marches.map((march) => march.distance_tiles);
    const estimatedSeconds = marches.flatMap((march) =>
      march.estimated_seconds === null ? [] : [march.estimated_seconds],
    );
    const qualityForAlliance = quality[allianceKey] ?? {
      violations: 0,
      warnings: 0,
      suggestions: 0,
    };

    result[allianceKey] = {
      counts,
      algorithm_version: 'territory-analysis-v2',
      territory_area_tiles: territoryArea,
      hq_anchored_components: anchored,
      disconnected_components: components - anchored,
      useful_banner_area_tiles: territoryArea - unionArea(hqTerritory),
      redundant_banner_count: redundantBanners,
      hive_density_percent:
        densityArea === null || densityArea < 1
          ? null
          : Math.round((10000 * unionArea(cityRects)) / densityArea) / 100,
      density_bounds_area_tiles: densityArea,
      assumptions: {
        city_coverage: 'entire_footprint',
        distance: 'euclidean_southwest_anchor',
        march_time: 'user_calibration_no_pathfinding',
        banner_efficiency: 'covered_cities_per_banner',
        density: 'city_footprint_union_over_city_bounds',
      },
      governor_cities: cities.length,
      covered_governor_cities: covered,
      uncovered_governor_cities: cities.length - covered,
      coverage_percent: cities.length ? Math.round((covered / cities.length) * 10000) / 100 : null,
      territory_components: components,
      territory_connected: components <= 1,
      banner_efficiency: counts.banner ? Math.round((covered / counts.banner) * 100) / 100 : null,
      violation_count: qualityForAlliance.violations,
      warning_count: qualityForAlliance.warnings,
      suggestion_count: qualityForAlliance.suggestions,
      bear_distance_tiles: stats(distances),
      estimated_march_seconds: seconds === undefined ? null : stats(estimatedSeconds),
      march_assumption_seconds_per_tile: seconds ?? null,
      selected_bear_trap_key: preferences.selected_bear_trap_by_alliance?.[allianceKey] ?? null,
      marches,
    };
  }

  return result;
}
