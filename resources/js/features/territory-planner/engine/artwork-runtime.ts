import type { PresentationImage } from './presentation.ts';
import type { ArtworkRegistry } from './artwork.ts';

/**
 * Lazy, bounded bridge between the shared presentation engine and the versioned artwork
 * registry. The registry and the manifest are only fetched when a scene actually needs
 * imagery, and every failure degrades to "no image" instead of fabricated artwork.
 */

type Runtime = { registry: ArtworkRegistry; version: string };

/** Content-addressed delivery URLs emitted by the bundler; empty until the art pack ships. */
const delivered = import.meta.glob('../../../../data/kingdom-map-art/assets/**/*', {
  query: '?url',
  import: 'default',
  eager: true,
}) as Record<string, string>;

let runtime: Runtime | null = null;
let loading: Promise<Runtime | null> | null = null;
let canvasRegistry: ArtworkRegistry | null = null;
let exportRegistry: ArtworkRegistry | null = null;
const listeners = new Set<() => void>();
const unavailable = new Set<string>();

function resolveUrl(path: string): string | null {
  return delivered[`../../../../data/kingdom-map-art/${path}`] ?? null;
}

function notify(): void {
  for (const listener of listeners) listener();
}

/** The delivered registry version, or null while the art pack is absent. */
export function artworkVersion(): string | null {
  return runtime?.version ?? null;
}

/** Synchronous presentation hook. It returns null until bytes are loaded and never invents art. */
export function artworkImage(assetKey: string, pixels: number): PresentationImage | null {
  return canvasRegistry?.image(assetKey, pixels) ?? null;
}

/** Registry paths that could not be delivered, for honest reporting in the UI. */
export function artworkUnavailable(): string[] {
  return [...unavailable];
}

export function onArtworkChange(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function create(mode: 'object-url' | 'data-url'): Promise<Runtime | null> {
  return import('./artwork.ts')
    .then(async (module) => {
      const manifest = await module.loadArtworkManifest();
      const registry = module.createArtworkRegistry(manifest, {
        mode,
        resolveUrl,
        onUnavailable: (key, reason) => {
          unavailable.add(key);
          if (import.meta.env.DEV)
            console.warn(`Kingdom Map artwork unavailable: ${key} (${reason})`);
        },
      });
      return { registry, version: manifest.registry_version };
    })
    .catch(() => null);
}

async function ensureRuntime(): Promise<Runtime | null> {
  if (runtime) return runtime;
  if (!loading) loading = create('object-url');
  const resolved = await loading;
  if (resolved) {
    runtime = resolved;
    canvasRegistry = resolved.registry;
  }
  return runtime;
}

/**
 * Loads the registry and the requested asset keys within the bounded cache. Resolves with the
 * keys whose imagery became available; unresolved keys stay on the typed fallback path.
 */
export async function ensureArtwork(assetKeys: readonly string[]): Promise<string[]> {
  const resolved = await ensureRuntime();
  if (!resolved) return [];
  const loaded = await resolved.registry.ensure(assetKeys);
  if (loaded.length) notify();
  return loaded;
}

/**
 * Prepares a data-URL registry so visual exports can embed verified artwork identity.
 * Returns null while no artwork is delivered, which keeps exports on the typed fallback.
 * With no explicit keys it prepares every delivered entry, bounded by the registry limits.
 */
export async function exportArtworkImage(
  assetKeys?: readonly string[],
): Promise<((assetKey: string, pixels: number) => PresentationImage | null) | null> {
  if (!exportRegistry) {
    const created = await create('data-url');
    if (!created) return null;
    exportRegistry = created.registry;
  }
  const wanted = assetKeys
    ? [...assetKeys]
    : exportRegistry.keys().filter((key) => exportRegistry?.resolve(key, 'sprite') !== null);
  if (!wanted.length) return null;
  await exportRegistry.ensure(wanted);
  return (assetKey, pixels) => exportRegistry?.image(assetKey, pixels) ?? null;
}

/**
 * Attaches the verified data-URL artwork hook to a set of presentation options so a visual
 * export embeds real artwork identity. Resolves false while no artwork is delivered, which
 * keeps the rendition on the typed fallback path instead of inventing imagery.
 */
export async function attachExportArtwork(options: {
  image?: (assetKey: string, pixels: number) => PresentationImage | null;
}): Promise<boolean> {
  const hook = await exportArtworkImage();
  if (!hook) return false;
  options.image = hook;
  return true;
}

/** Releases every decoded resource. Call from the owning surface's unmount path. */
export async function disposeArtwork(): Promise<void> {
  canvasRegistry?.dispose();
  exportRegistry?.dispose();
  canvasRegistry = null;
  exportRegistry = null;
  runtime = null;
  loading = null;
  unavailable.clear();
}
