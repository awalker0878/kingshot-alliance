import { buildTerritoryScene } from './scene.ts';
import { buildPresentation } from './presentation.ts';
import type { DrawingCommand, PresentationOptions } from './presentation';
import type { ObservedSceneObject } from './scene';
import type { MapData, PlanAlliance, PlanObject } from './types';

type WorldRectangle = { x: number; y: number; width: number; height: number };

export type ExportMetadata = {
  title: string;
  mapProfile: string;
  observedAt: string;
  confidence: string;
  exportedAt: string;
  mapChecksum?: string;
  planRevision?: number;
  artworkVersion?: string;
  workingDraft?: boolean;
  /** Locale and font provenance keep renditions reproducible across environments. */
  locale?: string;
  fontFamily?: string;
};

export type ExportScope = 'map' | 'viewport' | 'selection' | 'alliance';

export type ExportScopeInput = {
  scope: ExportScope;
  map: MapData;
  objects: readonly PlanObject[];
  selectedKeys?: readonly string[];
  activeAllianceKey?: string | null;
  viewport?: { x: number; y: number; width: number; height: number; zoom: number };
};

/** Largest export region the bounded raster allocation and SVG writer accept. */
export const MAX_EXPORT_DIMENSION = 20000;
export const MAX_EXPORT_PIXELS = 16_777_216;
/** The single font family every rendition text run uses; recorded as export provenance. */
export const EXPORT_FONT_FAMILY = 'sans-serif';

function unionRectangles(rectangles: readonly WorldRectangle[]): WorldRectangle | null {
  if (!rectangles.length) return null;
  let minX = Infinity;
  let minY = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;
  for (const rectangle of rectangles) {
    if (
      ![rectangle.x, rectangle.y, rectangle.width, rectangle.height].every(Number.isFinite) ||
      rectangle.width <= 0 ||
      rectangle.height <= 0
    )
      throw new RangeError('Invalid territory export region.');
    minX = Math.min(minX, rectangle.x);
    minY = Math.min(minY, rectangle.y);
    maxX = Math.max(maxX, rectangle.x + rectangle.width);
    maxY = Math.max(maxY, rectangle.y + rectangle.height);
  }
  return { x: minX, y: minY, width: maxX - minX, height: maxY - minY };
}

function clampRectangle(rectangle: WorldRectangle, bounds: WorldRectangle): WorldRectangle {
  const x = Math.max(bounds.x, Math.min(rectangle.x, bounds.x + bounds.width));
  const y = Math.max(bounds.y, Math.min(rectangle.y, bounds.y + bounds.height));
  const right = Math.max(x, Math.min(rectangle.x + rectangle.width, bounds.x + bounds.width));
  const bottom = Math.max(y, Math.min(rectangle.y + rectangle.height, bounds.y + bounds.height));
  return { x, y, width: Math.max(0, right - x), height: Math.max(0, bottom - y) };
}

function objectFootprints(
  map: MapData,
  objects: readonly PlanObject[],
  keys: readonly string[] | null,
): WorldRectangle[] {
  const wanted = keys ? new Set(keys) : null;
  const rectangles: WorldRectangle[] = [];
  for (const object of objects) {
    if (wanted && !wanted.has(object.key)) continue;
    const definition = map.object_types[object.type];
    if (!definition) throw new RangeError('Unsupported territory object type.');
    const rotated = object.rotation === 90 || object.rotation === 270;
    rectangles.push({
      x: object.x,
      y: object.y,
      width: rotated ? definition.footprint.height : definition.footprint.width,
      height: rotated ? definition.footprint.width : definition.footprint.height,
    });
  }
  return rectangles;
}

/**
 * Resolves one export scope to a bounded world region. Every scope is clamped to the map
 * release so a rendition can never expose geometry outside the authorized map.
 */
export function exportScopeBounds(input: ExportScopeInput): WorldRectangle {
  const mapBounds = input.map.bounds;
  assertRectangle(mapBounds);
  const padding = 2;
  let region: WorldRectangle | null = null;
  if (input.scope === 'viewport') {
    const viewport = input.viewport;
    if (viewport) {
      if (
        ![viewport.x, viewport.y, viewport.width, viewport.height, viewport.zoom].every(
          Number.isFinite,
        ) ||
        viewport.zoom <= 0 ||
        viewport.width <= 0 ||
        viewport.height <= 0
      )
        throw new RangeError('Invalid territory export viewport.');
      region = {
        x: viewport.x - viewport.width / viewport.zoom / 2,
        y: viewport.y - viewport.height / viewport.zoom / 2,
        width: viewport.width / viewport.zoom,
        height: viewport.height / viewport.zoom,
      };
    }
  } else if (input.scope === 'selection') {
    region = unionRectangles(objectFootprints(input.map, input.objects, input.selectedKeys ?? []));
  } else if (input.scope === 'alliance') {
    const objects = input.activeAllianceKey
      ? input.objects.filter((object) => object.alliance_key === input.activeAllianceKey)
      : input.objects;
    region = unionRectangles(objectFootprints(input.map, objects, null));
  }
  if (!region) return mapBounds;
  const padded = clampRectangle(
    {
      x: region.x - padding,
      y: region.y - padding,
      width: region.width + padding * 2,
      height: region.height + padding * 2,
    },
    mapBounds,
  );
  if (padded.width <= 0 || padded.height <= 0) return mapBounds;
  assertRectangle(padded);
  return padded;
}

/** Deterministic output-size estimate. Rejects regions that cannot be rasterized safely. */
export function estimateExportSize(
  bounds: WorldRectangle,
  scale = 1,
): { width: number; height: number; pixels: number } {
  if (
    ![bounds.x, bounds.y, bounds.width, bounds.height, scale].every(Number.isFinite) ||
    bounds.width <= 0 ||
    bounds.height <= 0 ||
    scale <= 0
  )
    throw new RangeError('Invalid territory export size.');
  const width = Math.ceil(bounds.width * scale);
  const height = Math.ceil(bounds.height * scale);
  if (width > MAX_EXPORT_DIMENSION || height > MAX_EXPORT_DIMENSION)
    throw new RangeError('Territory export exceeds the bounded raster dimension.');
  const pixels = width * height;
  if (pixels > MAX_EXPORT_PIXELS)
    throw new RangeError('Territory export exceeds the bounded raster allocation.');
  return { width, height, pixels };
}

function escapeXml(value: string): string {
  return xmlText(value).replace(
    /[&<>"']/g,
    (character) =>
      ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&apos;',
      })[character] ?? character,
  );
}

export function buildSvg(
  map: MapData,
  alliances: PlanAlliance[],
  objects: PlanObject[],
  metadata: ExportMetadata,
  options: PresentationOptions & {
    bounds?: WorldRectangle;
    observedObjects?: ObservedSceneObject[];
  } = {},
): string {
  if (
    alliances.length > 50 ||
    objects.length > 5000 ||
    map.structures.length > 1000 ||
    (map.facilities?.length ?? 0) > 1000 ||
    (map.terrain_features?.length ?? 0) > 20000 ||
    (map.resource_nodes?.length ?? 0) > 20000 ||
    (options.observedObjects?.length ?? 0) > 5000
  )
    throw new RangeError('Territory export exceeds supported scene limits.');
  assertRectangle(map.bounds);
  const bounds = options.bounds ?? map.bounds;
  assertRectangle(bounds);
  for (const object of objects) {
    const definition = map.object_types[object.type];
    if (!definition) throw new RangeError('Unsupported territory object type.');
    assertRectangle({ ...object, ...definition.footprint });
  }
  const scene = buildTerritoryScene({
    map,
    mapChecksum: metadata.mapChecksum ?? '',
    alliances,
    objects,
    observedObjects: options.observedObjects ?? [],
  });
  for (const entity of scene.entities) {
    assertRectangle(entity.bounds);
    for (const [x, y, width] of entity.spans ?? []) assertRectangle({ x, y, width, height: 1 });
  }
  for (const alliance of alliances) {
    if (!/^#[0-9a-f]{6}$/i.test(alliance.presentation_color))
      throw new RangeError('Invalid alliance color.');
  }
  const legendWidth = 260;
  const legendRows = alliances.map((alliance) => ({
    alliance,
    lines: wrapText(alliance.display_name, 25),
  }));
  const legendHeight =
    72 + legendRows.reduce((sum, row) => sum + Math.max(28, row.lines.length * 19 + 10), 0);
  const bodyHeight = Math.max(bounds.height, legendHeight);
  const footerWidth = Math.max(20, Math.floor((bounds.width + legendWidth - 40) / 8));
  const footerLines = [
    {
      lines: wrapText(metadata.title, Math.max(15, Math.floor(footerWidth * 0.65))),
      size: 20,
      color: '#f5d88a',
    },
    {
      lines: wrapText(
        `Map: ${metadata.mapProfile} · observed ${metadata.observedAt} · ${metadata.confidence}`,
        footerWidth,
      ),
      size: 13,
      color: '#b9c4cc',
    },
    {
      lines: wrapText(
        `Exported ${metadata.exportedAt} · Locale ${metadata.locale ?? 'en'} · Font ${metadata.fontFamily ?? EXPORT_FONT_FAMILY} · coordinates are planning data, not an official Century Games map claim.`,
        footerWidth,
      ),
      size: 12,
      color: '#87939c',
    },
  ];
  if (metadata.mapChecksum || metadata.planRevision !== undefined || metadata.artworkVersion)
    footerLines.push({
      lines: wrapText(
        `${metadata.workingDraft ? 'Unsaved working draft based on revision' : 'Revision'} ${metadata.planRevision ?? '—'} · Map ${map.id} · SHA-256 ${metadata.mapChecksum ?? '—'} · Artwork ${metadata.artworkVersion ?? 'unavailable'}`,
        footerWidth,
      ),
      size: 12,
      color: '#b9c4cc',
    });
  const footerHeight =
    32 + footerLines.reduce((sum, row) => sum + row.lines.length * (row.size + 6) + 8, 0);
  const parts: string[] = [];
  const viewWidth = bounds.width + legendWidth;
  const viewHeight = bodyHeight + footerHeight;

  parts.push(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${viewWidth}" height="${viewHeight}" viewBox="0 0 ${viewWidth} ${viewHeight}" role="img" aria-label="${escapeXml(metadata.title)}">`,
  );
  parts.push('<rect width="100%" height="100%" fill="#101821"/>');
  parts.push(
    `<rect x="0" y="0" width="${bounds.width}" height="${bounds.height}" fill="#17232d"/>`,
  );

  parts.push(
    `<defs><clipPath id="territory-map-clip"><rect width="${bounds.width}" height="${bounds.height}"/></clipPath></defs><g clip-path="url(#territory-map-clip)">`,
  );
  const commands = buildPresentation(
    scene,
    {
      x: bounds.x + bounds.width / 2,
      y: bounds.y + bounds.height / 2,
      width: bounds.width,
      height: bounds.height,
      zoom: 1,
    },
    options,
  );
  const images = new Map<string, string>();
  for (const command of commands)
    if (command.kind === 'image' && !images.has(command.href)) {
      if (
        !/^data:image\/(png|webp);base64,[a-zA-Z0-9+/=]+$/.test(command.href) ||
        command.href.length > 3_000_000
      )
        throw new RangeError('Territory SVG requires bounded embedded raster artwork.');
      images.set(command.href, `territory-art-${images.size}`);
    }
  if (
    images.size > 256 ||
    [...images.keys()].reduce((total, href) => total + href.length, 0) > 24_000_000
  )
    throw new RangeError('Territory SVG artwork exceeds the export byte limit.');
  parts.push('<defs>');
  for (const [href, id] of images)
    parts.push(
      `<image id="${id}" width="1" height="1" preserveAspectRatio="none" href="${href}"/>`,
    );
  parts.push('</defs>');
  parts.push(...commands.map((command) => svgCommand(command, images)));

  parts.push('</g>');
  parts.push(
    `<rect x="${bounds.width}" y="0" width="${legendWidth}" height="${bodyHeight}" fill="#0d151d"/>`,
  );
  parts.push(
    `<text x="${bounds.width + 24}" y="38" fill="#f5d88a" font-family="${EXPORT_FONT_FAMILY}" font-size="18" font-weight="700">Alliance legend</text>`,
  );
  let legendY = 72;
  for (const { alliance, lines } of legendRows) {
    parts.push(
      `<rect x="${bounds.width + 24}" y="${legendY - 13}" width="14" height="14" fill="${alliance.presentation_color}" opacity="${alliance.visible ? '1' : '.35'}"/>`,
    );
    lines.forEach((line, index) =>
      parts.push(
        `<text x="${bounds.width + 48}" y="${legendY + index * 19}" fill="#e8edf2" font-family="${EXPORT_FONT_FAMILY}" font-size="14">${escapeXml(line)}</text>`,
      ),
    );
    legendY += Math.max(28, lines.length * 19 + 10);
  }
  let footerY = bodyHeight + 30;
  for (const row of footerLines) {
    for (const line of row.lines) {
      parts.push(
        `<text x="20" y="${footerY}" fill="${row.color}" font-family="${EXPORT_FONT_FAMILY}" font-size="${row.size}">${escapeXml(line)}</text>`,
      );
      footerY += row.size + 6;
    }
    footerY += 8;
  }
  parts.push('</svg>');
  const svg = parts.join('');
  if (new TextEncoder().encode(svg).byteLength > 32_000_000)
    throw new RangeError('Territory SVG exceeds the export byte limit.');
  return svg;
}

function svgCommand(command: DrawingCommand, images: Map<string, string>): string {
  if (command.kind === 'text')
    return `<text x="${command.x}" y="${command.y}" fill="${escapeXml(command.fill)}" opacity="${command.opacity}" font-family="${EXPORT_FONT_FAMILY}" font-size="${command.size}">${escapeXml(command.text)}</text>`;
  if (command.kind === 'image') {
    return `<use href="#${images.get(command.href)}" transform="translate(${command.bounds.x} ${command.bounds.y}) scale(${command.bounds.width} ${command.bounds.height})" opacity="${command.opacity}"/>`;
  }
  const paint = `fill="${escapeXml(command.fill)}" opacity="${command.opacity}"${command.stroke ? ` stroke="${escapeXml(command.stroke)}" stroke-width="${command.strokeWidth ?? 1}"` : ''}${command.dash ? ` stroke-dasharray="${command.dash.join(' ')}"` : ''}`;
  if (command.kind === 'rect')
    return `<rect x="${command.bounds.x}" y="${command.bounds.y}" width="${command.bounds.width}" height="${command.bounds.height}" ${paint}/>`;
  const path = command.rectangles
    .map((rect) => `M${rect.x} ${rect.y}h${rect.width}v${rect.height}h${-rect.width}z`)
    .join('');
  return `<path d="${path}" ${paint}/>`;
}

/** Keep exported labels bounded while preserving Unicode code points and every line. */
function wrapText(value: string, columns: number): string[] {
  const clean = xmlText(value).trim();
  const lines: string[] = [];
  let line = '';
  for (const word of clean.split(/\s+/u)) {
    if (line && [...line, ' ', ...word].length > columns) {
      lines.push(line);
      line = '';
    }
    const chars = [...word];
    while (chars.length > columns) {
      lines.push(chars.splice(0, columns).join(''));
    }
    const tail = chars.join('');
    line = line ? `${line} ${tail}` : tail;
  }
  if (line || !lines.length) lines.push(line);
  return lines;
}

function xmlText(value: string): string {
  const characters = [...value];
  if (characters.length > 1024) throw new RangeError('Territory export label is too long.');
  return characters
    .map((character) => {
      const code = character.codePointAt(0)!;
      return (code < 32 && !'\t\n\r'.includes(character)) ||
        (code >= 0xd800 && code <= 0xdfff) ||
        code === 0xfffe ||
        code === 0xffff
        ? ' '
        : character;
    })
    .join('');
}

function assertRectangle(rect: WorldRectangle): void {
  if (
    ![rect.x, rect.y, rect.width, rect.height].every(Number.isFinite) ||
    Math.abs(rect.x) > 1_000_000 ||
    Math.abs(rect.y) > 1_000_000 ||
    rect.width <= 0 ||
    rect.height <= 0 ||
    rect.width > 20_000 ||
    rect.height > 20_000
  )
    throw new RangeError('Invalid territory export bounds.');
}

/** Validate the allocation before creating a canvas, never after allocating it. */
export function pngDimensions(
  width: number,
  sourceWidth: number,
  sourceHeight: number,
): { width: number; height: number } {
  if (
    !Number.isInteger(width) ||
    width < 1 ||
    width > 8192 ||
    !Number.isFinite(sourceWidth) ||
    !Number.isFinite(sourceHeight) ||
    sourceWidth <= 0 ||
    sourceHeight <= 0
  )
    throw new RangeError('Invalid territory PNG dimensions.');
  const height = Math.max(1, Math.round((width * sourceHeight) / sourceWidth));
  if (!Number.isFinite(height) || height > 8192 || width * height > 16_777_216)
    throw new RangeError('Territory PNG exceeds the 16 megapixel export limit.');
  return { width, height };
}

function downloadBlob(filename: string, blob: Blob): void {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  try {
    document.body.append(anchor);
    anchor.click();
  } finally {
    anchor.remove();
    // Let the browser consume the navigation before releasing the object URL.
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
}

export function downloadText(filename: string, content: string, type: string): void {
  downloadBlob(filename, new Blob([content], { type }));
}

export async function downloadPngFromSvg(
  filename: string,
  svg: string,
  width = 1800,
): Promise<void> {
  // Generated exports carry explicit dimensions, so even a failed image load cannot
  // bypass allocation limits. Parse as inert XML, never insert supplied markup into DOM.
  const document = new DOMParser().parseFromString(svg, 'image/svg+xml');
  const root = document.documentElement;
  if (
    root.localName !== 'svg' ||
    root.namespaceURI !== 'http://www.w3.org/2000/svg' ||
    document.querySelector('parsererror')
  )
    throw new Error('Invalid territory SVG export.');
  const dimensions = pngDimensions(
    width,
    Number(root.getAttribute('width')),
    Number(root.getAttribute('height')),
  );
  const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
  const image = new Image();
  let canvas: HTMLCanvasElement | null = null;
  let timer: ReturnType<typeof setTimeout> | null = null;
  try {
    await new Promise<void>((resolve, reject) => {
      timer = setTimeout(
        () => reject(new Error('Territory export image loading timed out.')),
        15_000,
      );
      image.onload = () => resolve();
      image.onerror = () => reject(new Error('Unable to render territory export.'));
      image.src = url;
    });
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
    canvas = window.document.createElement('canvas');
    canvas.width = dimensions.width;
    canvas.height = dimensions.height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Unable to render territory export.');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const png = await new Promise<Blob>((resolve, reject) =>
      canvas!.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('Unable to encode territory PNG.'))),
        'image/png',
      ),
    );
    downloadBlob(filename, png);
  } finally {
    if (timer !== null) clearTimeout(timer);
    image.onload = null;
    image.onerror = null;
    image.src = '';
    if (canvas) {
      canvas.width = 1;
      canvas.height = 1;
    }
    URL.revokeObjectURL(url);
  }
}
