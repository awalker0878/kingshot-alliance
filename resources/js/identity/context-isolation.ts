import { activePlayerFrom, type SharedPlayerContext } from '@/types/player-context';

type ContextDisposer = () => void;

const disposers = new Map<string, Set<ContextDisposer>>();
const frozenContexts = new Set<string>();

export function activeContextKey(context: SharedPlayerContext): string | null {
  return context.authorityContextVersion ?? activePlayerFrom(context)?.contextFingerprint.key ?? null;
}

export function platformScopedStorageKey(key: string): string {
  return `platform:${key}`;
}

export function playerScopedStorageKey(playerId: string, key: string): string {
  return `player:${playerId}:${key}`;
}

export function contextScopedStorageKey(contextKey: string, key: string): string {
  return `context:${contextKey}:${key}`;
}

export function registerContextDisposer(contextKey: string, disposer: ContextDisposer): () => void {
  const bucket = disposers.get(contextKey) ?? new Set<ContextDisposer>();
  bucket.add(disposer);
  disposers.set(contextKey, bucket);

  return () => {
    bucket.delete(disposer);
    if (bucket.size === 0) disposers.delete(contextKey);
  };
}

export function createContextAbortController(contextKey: string): AbortController {
  const controller = new AbortController();
  const unregister = registerContextDisposer(contextKey, () => controller.abort());
  controller.signal.addEventListener('abort', unregister, { once: true });

  return controller;
}

export function beginContextTransition(contextKey: string | null): void {
  if (!contextKey || frozenContexts.has(contextKey)) return;

  frozenContexts.add(contextKey);
  dispatchContextEvent('kingshot:context-freeze', contextKey);
  runContextDisposers(contextKey);
}

export function completeContextTransition(contextKey: string | null): void {
  if (!contextKey) return;

  runContextDisposers(contextKey);
  frozenContexts.delete(contextKey);
  dispatchContextEvent('kingshot:context-invalidated', contextKey);
}

export function cancelContextTransition(contextKey: string | null): void {
  if (!contextKey) return;

  frozenContexts.delete(contextKey);
  dispatchContextEvent('kingshot:context-thaw', contextKey);
}

export function isContextFrozen(contextKey: string | null): boolean {
  return contextKey !== null && frozenContexts.has(contextKey);
}

function runContextDisposers(contextKey: string): void {
  const bucket = disposers.get(contextKey);
  if (!bucket) return;

  disposers.delete(contextKey);
  for (const dispose of bucket) dispose();
}

function dispatchContextEvent(name: string, contextKey: string): void {
  if (typeof window === 'undefined') return;

  window.dispatchEvent(new CustomEvent(name, { detail: { contextKey } }));
}
