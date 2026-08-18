export type PlayerRoleIdentity = {
  key: string;
  name: string;
};

export type PlayerAllianceIdentity = {
  id: string;
  membershipId: string;
  name: string;
  rank: string;
  roles: PlayerRoleIdentity[];
  capabilities: string[];
};

export type PlayerContextFingerprint = {
  version: 1;
  key: string;
  playerId: string;
  kingdomId: string;
  kingdomNumber: number | null;
  allianceId: string | null;
  membershipId: string | null;
};

export type PlayerIdentity = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
  alliance: PlayerAllianceIdentity | null;
  contextFingerprint: PlayerContextFingerprint;
};

export type SharedPlayerContext = {
  activePlayerId: string | null;
  authorityContextVersion: string | null;
  players: PlayerIdentity[];
};

export const EMPTY_PLAYER_CONTEXT: SharedPlayerContext = {
  activePlayerId: null,
  authorityContextVersion: null,
  players: [],
};

export function activePlayerFrom(context: SharedPlayerContext): PlayerIdentity | null {
  if (!context.activePlayerId) return null;

  return context.players.find((player) => player.id === context.activePlayerId) ?? null;
}

export function playerHasCapability(player: PlayerIdentity | null, capability: string): boolean {
  return player?.alliance?.capabilities.includes(capability) ?? false;
}
