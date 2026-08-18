import { activeContextKey } from '@/identity/context-isolation';
import { EMPTY_PLAYER_CONTEXT, type SharedPlayerContext } from '@/types/player-context';

export const AUTHORITY_CONTEXT_HEADER = 'X-Game-Context-Version';
export const AUTHORITY_CONTEXT_ERROR_HEADER = 'X-Game-Context-Error';
export const AUTHORITY_CONTEXT_STALE_EVENT = 'kingshot:authority-context-stale';

let currentVersion: string | null = null;
let currentContextKey: string | null = null;

export function updateAuthorityContextFromPageProps(props: Record<string, unknown>): void {
  const context =
    (props.playerContext as SharedPlayerContext | undefined) ?? EMPTY_PLAYER_CONTEXT;

  currentVersion = context.authorityContextVersion;
  currentContextKey = activeContextKey(context);
}

export function authorityContextVersion(): string | null {
  return currentVersion;
}

export function authorityContextKey(): string | null {
  return currentContextKey;
}

export function authorityContextHeaders(
  headers: Record<string, string> = {},
): Record<string, string> {
  if (!currentVersion) return { ...headers };

  return {
    ...headers,
    [AUTHORITY_CONTEXT_HEADER]: currentVersion,
  };
}

export function isAuthorityContextStaleResponse(response: Response): boolean {
  return (
    response.status === 409 &&
    response.headers.get(AUTHORITY_CONTEXT_ERROR_HEADER)?.toLowerCase() === 'stale'
  );
}

export function dispatchAuthorityContextStale(): void {
  if (typeof window === 'undefined') return;

  window.dispatchEvent(new CustomEvent(AUTHORITY_CONTEXT_STALE_EVENT));
}
