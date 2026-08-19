import { activeContextKey } from '@/identity/context-isolation';
import { EMPTY_GAME_CONTEXT, type SharedGameContext } from '@/types/game-context';

export const AUTHORITY_CONTEXT_HEADER = 'X-Game-Context-Version';
export const AUTHORITY_CONTEXT_ERROR_HEADER = 'X-Game-Context-Error';
export const AUTHORITY_CONTEXT_STALE_EVENT = 'kingshot:authority-context-stale';

let currentVersion: string | null = null;
let currentContextKey: string | null = null;

export function updateAuthorityContextFromPageProps(props: Record<string, unknown>): void {
  const context = (props.gameContext as SharedGameContext | undefined) ?? EMPTY_GAME_CONTEXT;

  currentVersion = context.active?.authorityVersion ?? null;
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

export function isAuthorityContextStaleResponse(response: unknown): boolean {
  if (!response || typeof response !== 'object') return false;

  const candidate = response as { status?: unknown; headers?: unknown };
  if (candidate.status !== 409) return false;

  return (
    responseHeader(candidate.headers, AUTHORITY_CONTEXT_ERROR_HEADER)?.toLowerCase() === 'stale'
  );
}

export function dispatchAuthorityContextStale(): void {
  if (typeof window === 'undefined') return;

  window.dispatchEvent(new CustomEvent(AUTHORITY_CONTEXT_STALE_EVENT));
}

function responseHeader(headers: unknown, name: string): string | null {
  if (headers instanceof Headers) return headers.get(name);
  if (!headers || typeof headers !== 'object') return null;

  const source = headers as Record<string, unknown>;
  const direct = source[name] ?? source[name.toLowerCase()];
  if (typeof direct === 'string') return direct;
  if (Array.isArray(direct)) {
    const first = direct[0];
    return typeof first === 'string' ? first : null;
  }

  return null;
}
