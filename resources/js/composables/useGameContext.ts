import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import {
  EMPTY_GAME_CONTEXT,
  type GameContextView,
  type SharedGameContext,
  type SharedViewer,
} from '@/types/game-context';

export function useGameContext(): GameContextView {
  const page = usePage();
  const context = computed<SharedGameContext>(
    () =>
      ((page.props as Record<string, unknown>).gameContext as SharedGameContext | undefined) ??
      EMPTY_GAME_CONTEXT,
  );
  const viewer = computed<SharedViewer>(
    () => ((page.props as Record<string, unknown>).viewer as SharedViewer | undefined) ?? null,
  );
  const active = computed(() => context.value.active);
  const alliance = computed(() => active.value?.alliance ?? null);
  const kingdom = computed(() => active.value?.kingdom ?? null);

  return {
    context,
    viewer,
    governors: computed(() => context.value.governors),
    active,
    governor: computed(() => active.value?.governor ?? null),
    alliance,
    kingdom,
    navigation: computed(() => context.value.navigation),
    hasAllianceCapability: (capability: string): boolean =>
      alliance.value?.capabilities.includes(capability) ?? false,
    hasKingdomCapability: (capability: string): boolean =>
      kingdom.value?.capabilities.includes(capability) ?? false,
  };
}

export function useActiveGovernor() {
  return useGameContext().governor;
}

export function useAllianceContext() {
  return useGameContext().alliance;
}

export function useKingdomContext() {
  return useGameContext().kingdom;
}

export function useCapabilities() {
  const { hasAllianceCapability, hasKingdomCapability } = useGameContext();

  return { hasAllianceCapability, hasKingdomCapability };
}
