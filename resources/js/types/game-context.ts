import type { ComputedRef } from 'vue';

export type RoleIdentity = {
  key: string;
  name: string;
};

export type GovernorAllianceIdentity = {
  id: string;
  membershipId: string;
  name: string;
  rank: string;
  roles: RoleIdentity[];
};

export type GovernorIdentity = {
  id: string;
  name: string;
  gamePlayerId: string | null;
  kingdom: {
    id: string;
    number: number | null;
  };
  alliance: GovernorAllianceIdentity | null;
};

export type GameContextFingerprint = {
  version: 1;
  key: string;
  playerId: string;
  kingdomId: string;
  kingdomNumber: number | null;
  allianceId: string | null;
  membershipId: string | null;
};

export type ActiveGameContext = {
  governor: GovernorIdentity;
  kingdom: {
    id: string;
    number: number | null;
    capabilities: string[];
  };
  alliance: (GovernorAllianceIdentity & { capabilities: string[] }) | null;
  fingerprint: GameContextFingerprint;
  authorityVersion: string;
};

export type GameNavigationIcon =
  | 'dashboard'
  | 'alliance'
  | 'events'
  | 'roster'
  | 'recruitment'
  | 'content'
  | 'contributions'
  | 'kingdom'
  | 'transfers'
  | 'integrations';

export type GameNavigationItem = {
  key: GameNavigationIcon;
  href: string;
  icon: GameNavigationIcon;
  exact: boolean;
};

export type SharedGameContext = {
  version: 1;
  governors: GovernorIdentity[];
  active: ActiveGameContext | null;
  navigation: GameNavigationItem[];
};

export type SharedViewer = {
  id: number;
  name: string;
  email: string;
} | null;

export const EMPTY_GAME_CONTEXT: SharedGameContext = {
  version: 1,
  governors: [],
  active: null,
  navigation: [],
};

export type GameContextView = {
  context: ComputedRef<SharedGameContext>;
  viewer: ComputedRef<SharedViewer>;
  governors: ComputedRef<GovernorIdentity[]>;
  active: ComputedRef<ActiveGameContext | null>;
  governor: ComputedRef<GovernorIdentity | null>;
  alliance: ComputedRef<ActiveGameContext['alliance']>;
  kingdom: ComputedRef<ActiveGameContext['kingdom'] | null>;
  navigation: ComputedRef<GameNavigationItem[]>;
  hasAllianceCapability: (capability: string) => boolean;
  hasKingdomCapability: (capability: string) => boolean;
};
