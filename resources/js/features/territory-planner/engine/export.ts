import type { MapData, PlanAlliance, PlanObject } from './types';

type WorldRectangle = { x: number; y: number; width: number; height: number };

/** SVG uses a south-positive axis measured from the selected map's north-west corner. */
function exportRectangle(rect: WorldRectangle, bounds: WorldRectangle): WorldRectangle {
  return { ...rect, x: rect.x - bounds.x, y: bounds.y + bounds.height - rect.y - rect.height };
}

export type ExportMetadata = {
  title: string;
  mapProfile: string;
  observedAt: string;
  confidence: string;
  exportedAt: string;
};

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
): string {
  assertRectangle(map.bounds);
  if (alliances.length > 50 || objects.length > 5000 || map.structures.length > 1000)
    throw new RangeError('Territory export exceeds supported scene limits.');
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
  const bodyHeight = Math.max(map.bounds.height, legendHeight);
  const footerWidth = Math.max(20, Math.floor((map.bounds.width + legendWidth - 40) / 8));
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
        `Exported ${metadata.exportedAt} · coordinates are planning data, not an official Century Games map claim.`,
        footerWidth,
      ),
      size: 12,
      color: '#87939c',
    },
  ];
  const footerHeight =
    32 + footerLines.reduce((sum, row) => sum + row.lines.length * (row.size + 6) + 8, 0);
  const visible = new Set(
    alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key),
  );
  const colors = new Map(alliances.map((alliance) => [alliance.key, alliance.presentation_color]));
  const parts: string[] = [];
  const viewWidth = map.bounds.width + legendWidth;
  const viewHeight = bodyHeight + footerHeight;

  parts.push(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${viewWidth}" height="${viewHeight}" viewBox="0 0 ${viewWidth} ${viewHeight}" role="img" aria-label="${escapeXml(metadata.title)}">`,
  );
  parts.push('<rect width="100%" height="100%" fill="#101821"/>');
  parts.push(
    `<rect x="0" y="0" width="${map.bounds.width}" height="${map.bounds.height}" fill="#17232d"/>`,
  );

  parts.push(
    `<defs><clipPath id="territory-map-clip"><rect width="${map.bounds.width}" height="${map.bounds.height}"/></clipPath></defs><g clip-path="url(#territory-map-clip)">`,
  );
  for (const structure of map.structures) {
    assertRectangle({ ...structure, ...structure.footprint });
    const rect = exportRectangle({ ...structure, ...structure.footprint }, map.bounds);
    parts.push(
      `<rect x="${rect.x}" y="${rect.y}" width="${structure.footprint.width}" height="${structure.footprint.height}" fill="#8b7d6b" opacity="0.86"/>`,
    );
  }

  for (const object of objects) {
    if (!visible.has(object.alliance_key)) continue;
    const definition = map.object_types[object.type];
    if (!definition) throw new RangeError('Unsupported territory object type.');
    assertRectangle({ ...object, ...definition.footprint });
    const rect = exportRectangle({ ...object, ...definition.footprint }, map.bounds);
    const color = colors.get(object.alliance_key) ?? '#4da3ff';
    if (definition.coverage) {
      const coverageOffsetX = Math.trunc(
        (definition.coverage.width - definition.footprint.width) / 2,
      );
      const coverageOffsetY = Math.trunc(
        (definition.coverage.height - definition.footprint.height) / 2,
      );
      const coverageX = object.x - coverageOffsetX;
      const coverageY = object.y - coverageOffsetY;
      assertRectangle({ x: coverageX, y: coverageY, ...definition.coverage });
      const coverage = exportRectangle(
        { x: coverageX, y: coverageY, ...definition.coverage },
        map.bounds,
      );
      parts.push(
        `<rect x="${coverage.x}" y="${coverage.y}" width="${definition.coverage.width}" height="${definition.coverage.height}" fill="${color}" opacity="0.12"/>`,
      );
    }
    parts.push(
      `<rect x="${rect.x}" y="${rect.y}" width="${definition.footprint.width}" height="${definition.footprint.height}" fill="${color}" stroke="#fff" stroke-width="0.35"/>`,
    );
  }

  parts.push('</g>');
  parts.push(
    `<rect x="${map.bounds.width}" y="0" width="${legendWidth}" height="${bodyHeight}" fill="#0d151d"/>`,
  );
  parts.push(
    `<text x="${map.bounds.width + 24}" y="38" fill="#f5d88a" font-family="sans-serif" font-size="18" font-weight="700">Alliance legend</text>`,
  );
  let legendY = 72;
  for (const { alliance, lines } of legendRows) {
    parts.push(
      `<rect x="${map.bounds.width + 24}" y="${legendY - 13}" width="14" height="14" fill="${alliance.presentation_color}" opacity="${alliance.visible ? '1' : '.35'}"/>`,
    );
    lines.forEach((line, index) =>
      parts.push(
        `<text x="${map.bounds.width + 48}" y="${legendY + index * 19}" fill="#e8edf2" font-family="sans-serif" font-size="14">${escapeXml(line)}</text>`,
      ),
    );
    legendY += Math.max(28, lines.length * 19 + 10);
  }
  let footerY = bodyHeight + 30;
  for (const row of footerLines) {
    for (const line of row.lines) {
      parts.push(
        `<text x="20" y="${footerY}" fill="${row.color}" font-family="sans-serif" font-size="${row.size}">${escapeXml(line)}</text>`,
      );
      footerY += row.size + 6;
    }
    footerY += 8;
  }
  parts.push('</svg>');
  return parts.join('');
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
