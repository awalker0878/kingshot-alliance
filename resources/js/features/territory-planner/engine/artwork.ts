import type { PresentationImage } from './presentation.ts';
import type { TerritorySceneLayer } from './scene-types';

/**
 * Presentation-owned artwork registry. It maps stable scene asset keys to bounded,
 * versioned image representations. It never establishes game placement, collision or
 * mechanics authority, and it never fabricates imagery for a missing source.
 */

export type ArtworkRepresentationKind = 'icon' | 'sprite' | 'detail';
export type ArtworkReviewState =
  'not_required' | 'awaiting_source' | 'delivered_unreviewed' | 'reviewed';
export type ArtworkRenderer = 'raster' | 'vector';
export type ArtworkOrientation = 'upright' | 'rotational';

export type ArtworkAnchor = { x: number; y: number };

export type ArtworkRepresentation = {
  /** Content-addressed delivery path; it must contain the representation sha256. */
  path: string;
  sha256: string;
  mime_type: string;
  byte_size: number;
  width: number;
  height: number;
  anchor: ArtworkAnchor;
  orientation: ArtworkOrientation;
};

export type ArtworkEntry = {
  key: string;
  family: string;
  variant: string;
  label: string;
  category: string;
  layer: TerritorySceneLayer;
  renderer: ArtworkRenderer;
  orientation: ArtworkOrientation;
  anchor: ArtworkAnchor;
  world_width_tiles: number;
  required_representations: ArtworkRepresentationKind[];
  review_state: ArtworkReviewState;
  source: Record<string, unknown> | null;
  provenance: string[];
  rights_basis: string;
  representations: Record<ArtworkRepresentationKind, ArtworkRepresentation | null>;
};

export type ArtworkLimits = {
  max_source_bytes: number;
  max_dimension: number;
  max_registry_entries: number;
  max_total_assets: number;
  source_mime_types: string[];
  delivery_mime_types: string[];
};

export type ArtworkManifest = {
  schema_version: number;
  registry_version: string;
  limits: ArtworkLimits;
  representation_kinds: ArtworkRepresentationKind[];
  review_states: ArtworkReviewState[];
  entries: ArtworkEntry[];
};

export const ARTWORK_SCHEMA_VERSION = 1;
export const ARTWORK_REPRESENTATION_KINDS: ArtworkRepresentationKind[] = [
  'icon',
  'sprite',
  'detail',
];
export const ARTWORK_REVIEW_STATES: ArtworkReviewState[] = [
  'not_required',
  'awaiting_source',
  'delivered_unreviewed',
  'reviewed',
];
/** A missing source Outpost always degrades to this stable key rather than inventing art. */
export const ARTWORK_OUTPOST_FALLBACK_KEY = 'outpost.unknown';
/** Bounded decoded-resource cache; exports embed at most 256 images. */
export const ARTWORK_DEFAULT_CACHE_LIMIT = 256;

const SHA256 = /^[a-f0-9]{64}$/;
const KEY = /^[a-z0-9]+(?:_[a-z0-9]+)*\.[a-z0-9]+(?:_[a-z0-9]+)*$/;
const LAYERS: TerritorySceneLayer[] = [
  'terrain',
  'regions',
  'restrictions',
  'structures',
  'facilities',
  'resources',
  'coverage',
  'planned',
  'observed',
  'annotations',
  'validation',
];

export class ArtworkManifestError extends Error {
  readonly diagnostics: string[];

  constructor(diagnostics: string[]) {
    super(`Artwork manifest is invalid: ${diagnostics.join('; ')}`);
    this.name = 'ArtworkManifestError';
    this.diagnostics = diagnostics;
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function anchor(value: unknown, where: string, diagnostics: string[]): ArtworkAnchor | null {
  if (!isRecord(value)) {
    diagnostics.push(`${where}: anchor must be an object`);
    return null;
  }
  const x = value.x;
  const y = value.y;
  if (typeof x !== 'number' || !Number.isFinite(x) || x < 0 || x > 1) {
    diagnostics.push(`${where}: anchor.x must be a number in [0, 1]`);
    return null;
  }
  if (typeof y !== 'number' || !Number.isFinite(y) || y < 0 || y > 1) {
    diagnostics.push(`${where}: anchor.y must be a number in [0, 1]`);
    return null;
  }
  return { x, y };
}

function representation(
  value: unknown,
  where: string,
  limits: ArtworkLimits,
  diagnostics: string[],
): ArtworkRepresentation | null {
  if (value === null) return null;
  if (!isRecord(value)) {
    diagnostics.push(`${where}: representation must be null or an object`);
    return null;
  }
  const sha256 = value.sha256;
  if (typeof sha256 !== 'string' || !SHA256.test(sha256)) {
    diagnostics.push(`${where}: sha256 must be 64 lowercase hex characters`);
    return null;
  }
  const path = value.path;
  if (typeof path !== 'string' || path.length === 0) {
    diagnostics.push(`${where}: path must be a non-empty string`);
    return null;
  }
  if (!path.includes(sha256)) {
    diagnostics.push(`${where}: path must be content addressed (it must contain the sha256)`);
  }
  const mime = value.mime_type;
  if (typeof mime !== 'string' || !limits.delivery_mime_types.includes(mime)) {
    diagnostics.push(`${where}: mime_type must be a delivery MIME type`);
    return null;
  }
  const byteSize = value.byte_size;
  if (
    typeof byteSize !== 'number' ||
    !Number.isInteger(byteSize) ||
    byteSize <= 0 ||
    byteSize > limits.max_source_bytes
  ) {
    diagnostics.push(`${where}: byte_size must be a positive integer within max_source_bytes`);
    return null;
  }
  for (const dimension of ['width', 'height'] as const) {
    const size = value[dimension];
    if (
      typeof size !== 'number' ||
      !Number.isInteger(size) ||
      size <= 0 ||
      size > limits.max_dimension
    ) {
      diagnostics.push(`${where}: ${dimension} must be a positive integer within max_dimension`);
      return null;
    }
  }
  const orientation = value.orientation;
  if (orientation !== 'upright' && orientation !== 'rotational') {
    diagnostics.push(`${where}: orientation must be upright or rotational`);
    return null;
  }
  const resolvedAnchor = anchor(value.anchor, where, diagnostics);
  if (!resolvedAnchor) return null;
  return {
    path,
    sha256,
    mime_type: mime,
    byte_size: byteSize,
    width: value.width as number,
    height: value.height as number,
    anchor: resolvedAnchor,
    orientation,
  };
}

/** Structural validation of the versioned registry. Returns every diagnostic it can find. */
export function validateArtworkManifest(value: unknown): string[] {
  const diagnostics: string[] = [];
  if (!isRecord(value)) return ['manifest must be an object'];
  if (value.schema_version !== ARTWORK_SCHEMA_VERSION)
    diagnostics.push(`schema_version must be ${ARTWORK_SCHEMA_VERSION}`);
  if (typeof value.registry_version !== 'string' || value.registry_version.length === 0)
    diagnostics.push('registry_version must be a non-empty string');

  const rawLimits = isRecord(value.limits) ? value.limits : {};
  if (!isRecord(value.limits)) diagnostics.push('limits must be an object');
  const limits: ArtworkLimits = {
    max_source_bytes: Number(rawLimits.max_source_bytes),
    max_dimension: Number(rawLimits.max_dimension),
    max_registry_entries: Number(rawLimits.max_registry_entries),
    max_total_assets: Number(rawLimits.max_total_assets),
    source_mime_types: Array.isArray(rawLimits.source_mime_types)
      ? rawLimits.source_mime_types.filter((item): item is string => typeof item === 'string')
      : [],
    delivery_mime_types: Array.isArray(rawLimits.delivery_mime_types)
      ? rawLimits.delivery_mime_types.filter((item): item is string => typeof item === 'string')
      : [],
  };
  for (const key of [
    'max_source_bytes',
    'max_dimension',
    'max_registry_entries',
    'max_total_assets',
  ] as const)
    if (!Number.isFinite(limits[key]) || limits[key] <= 0)
      diagnostics.push(`limits.${key} must be positive`);
  for (const key of ['source_mime_types', 'delivery_mime_types'] as const)
    if (!limits[key].length) diagnostics.push(`limits.${key} must list at least one MIME type`);
  for (const mime of limits.delivery_mime_types)
    if (!limits.source_mime_types.includes(mime))
      diagnostics.push(
        `limits.delivery_mime_types must be a subset of source_mime_types (${mime})`,
      );

  if (!Array.isArray(value.representation_kinds))
    diagnostics.push('representation_kinds must be an array');
  if (!Array.isArray(value.review_states)) diagnostics.push('review_states must be an array');
  if (!Array.isArray(value.entries)) {
    diagnostics.push('entries must be an array');
    return diagnostics;
  }
  if (value.entries.length > limits.max_registry_entries)
    diagnostics.push(
      `entries must not exceed limits.max_registry_entries (${limits.max_registry_entries})`,
    );

  const seen = new Set<string>();
  let assetCount = 0;
  for (const raw of value.entries) {
    if (!isRecord(raw)) {
      diagnostics.push('every entry must be an object');
      continue;
    }
    const key = typeof raw.key === 'string' ? raw.key : '<missing>';
    if (!KEY.test(key))
      diagnostics.push(`${key}: key must be a lowercase dot-separated stable key`);
    if (seen.has(key)) diagnostics.push(`${key}: duplicate entry key`);
    seen.add(key);
    if (typeof raw.label !== 'string' || raw.label.length === 0)
      diagnostics.push(`${key}: label must be a non-empty string`);
    if (typeof raw.category !== 'string' || raw.category.length === 0)
      diagnostics.push(`${key}: category must be a non-empty string`);
    if (typeof raw.layer !== 'string' || !LAYERS.includes(raw.layer as TerritorySceneLayer))
      diagnostics.push(`${key}: layer must be a scene layer`);
    if (raw.renderer !== 'raster' && raw.renderer !== 'vector')
      diagnostics.push(`${key}: renderer must be raster or vector`);
    if (raw.orientation !== 'upright' && raw.orientation !== 'rotational')
      diagnostics.push(`${key}: orientation must be upright or rotational`);
    if (!anchor(raw.anchor, key, diagnostics)) {
      // anchor() already recorded the diagnostic
    }
    if (typeof raw.world_width_tiles !== 'number' || raw.world_width_tiles <= 0)
      diagnostics.push(`${key}: world_width_tiles must be positive`);
    if (typeof raw.rights_basis !== 'string' || raw.rights_basis.length === 0)
      diagnostics.push(`${key}: rights_basis must be a non-empty string`);
    if (!Array.isArray(raw.provenance)) diagnostics.push(`${key}: provenance must be an array`);
    if (raw.source !== null && !isRecord(raw.source))
      diagnostics.push(`${key}: source must be null or an object`);
    if (
      typeof raw.review_state !== 'string' ||
      !ARTWORK_REVIEW_STATES.includes(raw.review_state as ArtworkReviewState)
    )
      diagnostics.push(`${key}: review_state must be a known review state`);
    if (!Array.isArray(raw.required_representations)) {
      diagnostics.push(`${key}: required_representations must be an array`);
    } else {
      for (const kind of raw.required_representations)
        if (!ARTWORK_REPRESENTATION_KINDS.includes(kind as ArtworkRepresentationKind))
          diagnostics.push(`${key}: unknown required representation ${String(kind)}`);
    }
    if (!isRecord(raw.representations)) {
      diagnostics.push(`${key}: representations must be an object`);
      continue;
    }
    for (const kind of ARTWORK_REPRESENTATION_KINDS) {
      const resolved = representation(
        raw.representations[kind],
        `${key}.${kind}`,
        limits,
        diagnostics,
      );
      if (resolved) assetCount++;
    }
  }
  if (assetCount > limits.max_total_assets)
    diagnostics.push(
      `representation count must not exceed limits.max_total_assets (${limits.max_total_assets})`,
    );
  return diagnostics;
}

/** Parses and validates the registry, throwing when the shape is unusable at runtime. */
export function parseArtworkManifest(value: unknown): ArtworkManifest {
  const diagnostics = validateArtworkManifest(value);
  if (diagnostics.length) throw new ArtworkManifestError(diagnostics);
  return value as ArtworkManifest;
}

export type ArtworkStatus = {
  requestedKey: string;
  resolvedKey: string | null;
  available: boolean;
  reviewState: ArtworkReviewState | null;
  missing: ArtworkRepresentationKind[];
};

export type ArtworkCoverage = {
  total: number;
  covered: number;
  missing: string[];
  awaitingSource: string[];
};

export type ArtworkLoaded = { href: string; revoke?: () => void };
export type ArtworkAssetLoader = (url: string) => Promise<ArtworkLoaded>;

export type ArtworkRegistryOptions = {
  baseUrl?: string;
  loader?: ArtworkAssetLoader;
  /** `object-url` keeps bytes in the browser heap; `data-url` produces export-embeddable hrefs. */
  mode?: 'object-url' | 'data-url';
  cacheLimit?: number;
  fetchImpl?: typeof fetch;
  /** Resolves a registry path to a delivered URL. Returning null marks the asset unavailable. */
  resolveUrl?: (path: string) => string | null;
  onUnavailable?: (assetKey: string, reason: string) => void;
};

export type ArtworkRegistry = {
  readonly version: string;
  keys(): string[];
  entry(key: string): ArtworkEntry | null;
  resolve(key: string, kind?: ArtworkRepresentationKind): ArtworkRepresentation | null;
  status(key: string): ArtworkStatus;
  coverage(keys: readonly string[]): ArtworkCoverage;
  /** Synchronous presentation hook. It returns null until bytes are loaded and never fabricates. */
  image(assetKey: string, pixels: number): PresentationImage | null;
  /** Bounded, lazy byte loading. Failures are recorded as unavailable, never as imagery. */
  ensure(assetKeys: readonly string[]): Promise<string[]>;
  loaded(): string[];
  unavailable(): string[];
  dispose(): void;
};

function resolveRepresentation(
  entry: ArtworkEntry,
  kind: ArtworkRepresentationKind,
): ArtworkRepresentation | null {
  const direct = entry.representations[kind];
  if (direct) return direct;
  for (const fallback of ARTWORK_REPRESENTATION_KINDS)
    if (entry.representations[fallback]) return entry.representations[fallback];
  return null;
}

function defaultLoader(
  mode: 'object-url' | 'data-url',
  fetchImpl: typeof fetch,
): ArtworkAssetLoader {
  return async (url) => {
    const response = await fetchImpl(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`artwork request failed with status ${response.status}`);
    const buffer = await response.arrayBuffer();
    if (mode === 'data-url') {
      const mime =
        response.headers.get('content-type')?.split(';')[0]?.trim() || 'application/octet-stream';
      let binary = '';
      const bytes = new Uint8Array(buffer);
      for (let index = 0; index < bytes.length; index += 1)
        binary += String.fromCharCode(bytes[index]!);
      return { href: `data:${mime};base64,${btoa(binary)}` };
    }
    const objectUrl = URL.createObjectURL(new Blob([buffer]));
    return { href: objectUrl, revoke: () => URL.revokeObjectURL(objectUrl) };
  };
}

export function createArtworkRegistry(
  manifest: ArtworkManifest,
  options: ArtworkRegistryOptions = {},
): ArtworkRegistry {
  const byKey = new Map(manifest.entries.map((entry) => [entry.key, entry]));
  const cacheLimit = options.cacheLimit ?? ARTWORK_DEFAULT_CACHE_LIMIT;
  const mode = options.mode ?? 'object-url';
  const loader = options.loader ?? defaultLoader(mode, options.fetchImpl ?? fetch);
  const baseUrl = (options.baseUrl ?? '/').replace(/\/+$/, '');
  const loadedHrefs = new Map<string, ArtworkLoaded>();
  const failed = new Map<string, string>();
  const pending = new Map<string, Promise<string[]>>();

  function resolveKey(key: string): string | null {
    if (byKey.has(key)) return key;
    if (key.startsWith('outpost.'))
      return byKey.has(ARTWORK_OUTPOST_FALLBACK_KEY) ? ARTWORK_OUTPOST_FALLBACK_KEY : null;
    return null;
  }

  function evict(): void {
    while (loadedHrefs.size > cacheLimit) {
      const oldest = loadedHrefs.keys().next();
      if (oldest.done) return;
      loadedHrefs.get(oldest.value)?.revoke?.();
      loadedHrefs.delete(oldest.value);
    }
  }

  return {
    version: manifest.registry_version,
    keys: () => [...byKey.keys()],
    entry: (key) => {
      const resolved = resolveKey(key);
      return resolved ? (byKey.get(resolved) ?? null) : null;
    },
    resolve: (key, kind = 'sprite') => {
      const resolved = resolveKey(key);
      if (!resolved) return null;
      const entry = byKey.get(resolved);
      if (!entry || entry.renderer !== 'raster') return null;
      return resolveRepresentation(entry, kind);
    },
    status: (key) => {
      const resolved = resolveKey(key);
      const entry = resolved ? (byKey.get(resolved) ?? null) : null;
      if (!entry)
        return {
          requestedKey: key,
          resolvedKey: null,
          available: false,
          reviewState: null,
          missing: [...ARTWORK_REPRESENTATION_KINDS],
        };
      const missing = entry.required_representations.filter((kind) => !entry.representations[kind]);
      return {
        requestedKey: key,
        resolvedKey: entry.key,
        available: entry.renderer === 'vector' || missing.length === 0,
        reviewState: entry.review_state,
        missing,
      };
    },
    coverage: (keys) => {
      const missing: string[] = [];
      const awaitingSource: string[] = [];
      let covered = 0;
      for (const key of new Set(keys)) {
        const entry = resolveKey(key) ? byKey.get(resolveKey(key)!) : null;
        if (!entry) {
          missing.push(key);
          continue;
        }
        if (entry.review_state === 'awaiting_source') awaitingSource.push(key);
        if (entry.required_representations.some((kind) => !entry.representations[kind]))
          missing.push(key);
        else covered += 1;
      }
      return { total: new Set(keys).size, covered, missing, awaitingSource };
    },
    image: (assetKey, pixels) => {
      const resolved = resolveKey(assetKey);
      if (!resolved) return null;
      const entry = byKey.get(resolved);
      if (!entry || entry.renderer !== 'raster') return null;
      // Prefer the fidelity the viewport asked for, then any already-decoded representation of the same
      // entry. A lower-fidelity variant of the real asset is honest; invented artwork is not.
      const preferred: ArtworkRepresentationKind = pixels >= 512 ? 'detail' : 'sprite';
      for (const kind of [
        preferred,
        ...ARTWORK_REPRESENTATION_KINDS.filter((item) => item !== preferred),
      ]) {
        const representation = entry.representations[kind];
        if (!representation) continue;
        const asset = loadedHrefs.get(representation.path);
        if (!asset) continue;
        return {
          href: asset.href,
          width: representation.width,
          height: representation.height,
          anchor: representation.anchor,
          worldWidth: entry.world_width_tiles,
        };
      }
      return null;
    },
    ensure: (assetKeys) => {
      const wanted = new Map<string, ArtworkRepresentation>();
      for (const key of new Set(assetKeys)) {
        const resolved = resolveKey(key);
        if (!resolved) {
          failed.set(key, 'no registry entry');
          options.onUnavailable?.(key, 'no registry entry');
          continue;
        }
        const entry = byKey.get(resolved)!;
        if (entry.renderer !== 'raster') continue;
        // `ensure` means "make this key renderable at every fidelity the manifest declares", so the
        // canvas and the exporter can both pick from real delivered bytes rather than a substitute.
        const required = entry.required_representations.length
          ? entry.required_representations
          : [...ARTWORK_REPRESENTATION_KINDS];
        const delivered = required
          .map((kind) => entry.representations[kind])
          .filter((item): item is ArtworkRepresentation => Boolean(item));
        if (!delivered.length) {
          failed.set(resolved, `awaiting ${required.join('/')} source`);
          options.onUnavailable?.(resolved, `awaiting ${required.join('/')} source`);
          continue;
        }
        for (const representation of delivered)
          if (!loadedHrefs.has(representation.path))
            wanted.set(representation.path, representation);
      }
      if (!wanted.size) return Promise.resolve([]);
      const signature = [...wanted.keys()].sort().join('|');
      const existing = pending.get(signature);
      if (existing) return existing;
      const job = Promise.all(
        [...wanted].map(async ([path, representation]) => {
          const url = options.resolveUrl
            ? options.resolveUrl(representation.path)
            : `${baseUrl}/${representation.path.replace(/^\/+/, '')}`;
          if (!url) {
            failed.set(path, 'no delivered URL for the registry path');
            options.onUnavailable?.(path, 'no delivered URL for the registry path');
            return null;
          }
          try {
            const asset = await loader(url);
            loadedHrefs.set(path, asset);
            failed.delete(path);
            evict();
            return path;
          } catch (error) {
            const reason = error instanceof Error ? error.message : 'artwork load failed';
            failed.set(path, reason);
            options.onUnavailable?.(path, reason);
            return null;
          }
        }),
      )
        .then((results) => results.filter((path): path is string => path !== null))
        .finally(() => pending.delete(signature));
      pending.set(signature, job);
      return job;
    },
    loaded: () => [...loadedHrefs.keys()],
    unavailable: () => [...failed.keys()],
    dispose: () => {
      for (const asset of loadedHrefs.values()) asset.revoke?.();
      loadedHrefs.clear();
      failed.clear();
      pending.clear();
    },
  };
}

/** Reads the versioned registry that ships with the application, validating its shape. */
export async function loadArtworkManifest(
  load: () => Promise<{ default?: unknown } | unknown> = () =>
    import('../../../../data/kingdom-map-art/manifest.v1.json'),
): Promise<ArtworkManifest> {
  const module = await load();
  const value = isRecord(module) && 'default' in module ? module.default : module;
  return parseArtworkManifest(value);
}
