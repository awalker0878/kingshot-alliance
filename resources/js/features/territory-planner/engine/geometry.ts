import type {
  AllianceAnalysis,
  MapData,
  PlanObject,
  PlanningPreferences,
  ValidationIssue,
  ValidationResult,
} from './types';

type Rect = { x: number; y: number; width: number; height: number };
type CoverageSource = { key: string; x: number; y: number; coverage: number };

function rectFor(object: PlanObject, map: MapData): Rect | null {
  const definition = map.object_types[object.type];
  if (!definition || definition.size < 1) return null;
  return { x: object.x, y: object.y, width: definition.size, height: definition.size };
}

function intersects(a: Rect, b: Rect): boolean {
  return (
    a.x < b.x + b.width &&
    a.x + a.width > b.x &&
    a.y < b.y + b.height &&
    a.y + a.height > b.y
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
    const definition = map.object_types[object.type];
    if (!definition || definition.coverage <= 0 || definition.size <= 0) return [];
    return [
      {
        key: object.key,
        x: object.x + definition.size / 2,
        y: object.y + definition.size / 2,
        coverage: definition.coverage,
      },
    ];
  });
}

function coverageComponentCount(sources: CoverageSource[]): number {
  if (!sources.length) return 0;
  const visited = new Set<number>();
  let components = 0;
  sources.forEach((source, start) => {
    if (visited.has(start)) return;
    components += 1;
    const queue = [start];
    while (queue.length) {
      const index = queue.pop();
      if (index === undefined || visited.has(index)) continue;
      visited.add(index);
      const current = sources[index];
      if (!current) continue;
      sources.forEach((candidate, candidateIndex) => {
        if (visited.has(candidateIndex)) return;
        const distance = Math.max(
          Math.abs(current.x - candidate.x),
          Math.abs(current.y - candidate.y),
        );
        if (distance <= current.coverage + candidate.coverage) queue.push(candidateIndex);
      });
    }
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

    for (const structure of map.structures) {
      const exclusion = Math.max(structure.exclusion, 0);
      const actual = {
        x: structure.x,
        y: structure.y,
        width: structure.size,
        height: structure.size,
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
      if (exclusion === 0) continue;
      const forbidden = {
        x: structure.x - exclusion,
        y: structure.y - exclusion,
        width: structure.size + exclusion * 2,
        height: structure.size + exclusion * 2,
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
    if (sources.length > 1 && coverageComponentCount(sources) > 1) {
      warnings.push(
        issue(
          'disconnected_territory',
          'Alliance territory coverage is split into disconnected regions.',
          sources[0]?.key,
        ),
      );
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
    const covered = cities.filter((city) => {
      const size = map.object_types.governor_city.size;
      const corners = [
        [city.x, city.y],
        [city.x + size, city.y],
        [city.x, city.y + size],
        [city.x + size, city.y + size],
      ];
      return corners.every(([x, y]) =>
        sources.some(
          (source) =>
            Math.abs((x ?? 0) - source.x) <= source.coverage &&
            Math.abs((y ?? 0) - source.y) <= source.coverage,
        ),
      );
    }).length;

    const components = coverageComponentCount(sources);
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
