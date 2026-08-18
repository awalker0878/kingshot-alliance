export type PlayerRoleIdentity = {
  key: string;
  name: string;
};

export type PlayerAllianceIdentity = {
  id: string;
  name: string;
  rank: string;
  roles: PlayerRoleIdentity[];
  capabilities: string[];
};

export type PlayerIdentity = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdomNumber: number | null;
  alliance: PlayerAllianceIdentity | null;
};

export type SharedPlayerContext = {
  activePlayerId: string | null;
  players: PlayerIdentity[];
};

export const EMPTY_PLAYER_CONTEXT: SharedPlayerContext = {
  activePlayerId: null,
  players: [],
};

export function activePlayerFrom(context: SharedPlayerContext): PlayerIdentity | null {
  if (!context.activePlayerId) return null;

  return context.players.find((player) => player.id === context.activePlayerId) ?? null;
}

export function playerHasCapability(player: PlayerIdentity | null, capability: string): boolean {
  return player?.alliance?.capabilities.includes(capability) ?? false;
}
