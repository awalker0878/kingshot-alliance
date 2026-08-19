import { router } from '@inertiajs/vue3';

import { useGameContext } from '@/composables/useGameContext';
import {
  activeContextKey,
  beginContextTransition,
  cancelContextTransition,
  completeContextTransition,
} from '@/identity/context-isolation';

export type GovernorRouteIntent = {
  governorId: string;
  path: string;
};

function safeLocalPath(path: string): string {
  if (!path.startsWith('/') || path.startsWith('//')) return '/dashboard';

  try {
    const parsed = new URL(path, window.location.origin);
    if (parsed.origin !== window.location.origin) return '/dashboard';

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
  } catch {
    return '/dashboard';
  }
}

export function useGovernorNavigation() {
  const { context, governor, governors } = useGameContext();

  function visit(intent: GovernorRouteIntent): void {
    const destination = safeLocalPath(intent.path);
    const targetIsOwned = governors.value.some((candidate) => candidate.id === intent.governorId);

    if (!targetIsOwned) {
      router.visit('/dashboard', { preserveState: false, preserveScroll: false });
      return;
    }

    if (governor.value?.id === intent.governorId) {
      router.visit(destination, { preserveState: false, preserveScroll: false });
      return;
    }

    const previousContextKey = activeContextKey(context.value);
    beginContextTransition(previousContextKey);

    router.post(
      `/players/${intent.governorId}/activate`,
      { returnTo: destination },
      {
        preserveState: false,
        preserveScroll: false,
        onSuccess: () => completeContextTransition(previousContextKey),
        onError: () => cancelContextTransition(previousContextKey),
      },
    );
  }

  return { visit };
}
