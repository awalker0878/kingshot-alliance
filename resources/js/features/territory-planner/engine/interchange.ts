import type { PlanAlliance, PlanGroup, PlanObject, PlanningPreferences } from './types';

export type LayoutPlanIdentity = {
  id: string;
  scope: 'alliance' | 'kingdom';
  kingdom_id: string;
  owner_alliance_id: string | null;
  name: string;
};

/** Exact canonical interchange fields; UI capabilities and mutable status are never exported. */
export function buildLayoutDocument(
  plan: LayoutPlanIdentity,
  revision: number,
  mapId: string,
  mapChecksum: string,
  layout: {
    alliances: PlanAlliance[];
    groups: PlanGroup[];
    objects: PlanObject[];
    preferences: PlanningPreferences;
  },
) {
  if (!Number.isSafeInteger(revision) || revision < 1 || !/^[a-f0-9]{64}$/.test(mapChecksum))
    throw new RangeError('Layout export requires a valid revision and pinned map checksum.');
  if (layout.alliances.length > 50 || layout.groups.length > 500 || layout.objects.length > 5000)
    throw new RangeError('Layout export exceeds supported limits.');
  return {
    schema_version: 2 as const,
    plan: {
      id: plan.id,
      scope: plan.scope,
      kingdom_id: plan.kingdom_id,
      owner_alliance_id: plan.owner_alliance_id,
      name: plan.name,
      head_revision: revision,
      map_dataset_id: mapId,
      map_dataset_checksum: mapChecksum,
      planning_preferences: layout.preferences,
    },
    alliances: layout.alliances,
    groups: layout.groups,
    objects: layout.objects,
  };
}
