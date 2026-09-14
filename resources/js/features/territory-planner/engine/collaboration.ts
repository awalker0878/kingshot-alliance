export type CollaborationGrant = {
  id: string;
  player_id: string;
  alliance_key: string;
  permission: 'review' | 'edit';
  expires_at: string;
  revoked_at: string | null;
};

export type CollaborationOverview = {
  current_revision: number;
  current_snapshot_checksum: string;
  can_manage: boolean;
  can_review: boolean;
  editable_alliance_keys: string[];
  grants: CollaborationGrant[];
};

export function editableAllianceKeys(overview: CollaborationOverview | null): Set<string> {
  return new Set(overview?.editable_alliance_keys ?? []);
}

export function reviewableAllianceKeys(
  overview: CollaborationOverview | null,
  allAllianceKeys: string[],
  now = Date.now(),
): Set<string> {
  if (!overview) return new Set();
  if (overview.can_manage) return new Set(allAllianceKeys);
  return new Set(
    overview.grants
      .filter(
        (grant) =>
          grant.revoked_at === null &&
          Date.parse(grant.expires_at) > now &&
          (grant.permission === 'review' || grant.permission === 'edit'),
      )
      .map((grant) => grant.alliance_key),
  );
}

export function uniquePlayerOptions(
  byAlliance: Record<string, Array<{ id: string; name: string }>>,
): Array<{ id: string; name: string }> {
  const players = new Map<string, string>();
  for (const options of Object.values(byAlliance)) {
    for (const option of options) if (!players.has(option.id)) players.set(option.id, option.name);
  }
  return [...players.entries()]
    .map(([id, name]) => ({ id, name }))
    .sort((left, right) => left.name.localeCompare(right.name));
}

export function privateShareFragment(shareId: string, token: string): string {
  return `/territory/shared/${encodeURIComponent(shareId)}#token=${encodeURIComponent(token)}`;
}
