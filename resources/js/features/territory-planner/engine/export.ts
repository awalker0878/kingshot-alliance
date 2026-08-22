import type { MapData, PlanAlliance, PlanObject } from './types';

export type ExportMetadata = {
  title: string;
  mapProfile: string;
  observedAt: string;
  confidence: string;
  exportedAt: string;
};

function escapeXml(value: string): string {
  return value.replace(
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
  const legendWidth = 260;
  const footerHeight = Math.max(110, 64 + alliances.length * 24);
  const visible = new Set(
    alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key),
  );
  const colors = new Map(alliances.map((alliance) => [alliance.key, alliance.presentation_color]));
  const parts: string[] = [];
  const viewWidth = map.bounds.width + legendWidth;
  const viewHeight = map.bounds.height + footerHeight;

  parts.push(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${viewWidth} ${viewHeight}" role="img" aria-label="${escapeXml(metadata.title)}">`,
  );
  parts.push('<rect width="100%" height="100%" fill="#101821"/>');
  parts.push(
    `<rect x="0" y="0" width="${map.bounds.width}" height="${map.bounds.height}" fill="#17232d"/>`,
  );

  for (const structure of map.structures) {
    parts.push(
      `<rect x="${structure.x}" y="${map.bounds.height - structure.y - structure.size}" width="${structure.size}" height="${structure.size}" fill="#8b7d6b" opacity="0.86"/>`,
    );
  }

  for (const object of objects) {
    if (!visible.has(object.alliance_key)) continue;
    const definition = map.object_types[object.type];
    if (!definition) continue;
    const color = colors.get(object.alliance_key) ?? '#4da3ff';
    if (definition.coverage > 0) {
      parts.push(
        `<rect x="${object.x - definition.coverage}" y="${map.bounds.height - object.y - definition.size - definition.coverage}" width="${definition.size + definition.coverage * 2}" height="${definition.size + definition.coverage * 2}" fill="${color}" opacity="0.12"/>`,
      );
    }
    parts.push(
      `<rect x="${object.x}" y="${map.bounds.height - object.y - definition.size}" width="${definition.size}" height="${definition.size}" fill="${color}" stroke="#fff" stroke-width="0.35"/>`,
    );
  }

  parts.push(
    `<rect x="${map.bounds.width}" y="0" width="${legendWidth}" height="${map.bounds.height}" fill="#0d151d"/>`,
  );
  parts.push(
    `<text x="${map.bounds.width + 24}" y="38" fill="#f5d88a" font-family="sans-serif" font-size="18" font-weight="700">Alliance legend</text>`,
  );
  alliances.forEach((alliance, index) => {
    const y = 72 + index * 28;
    parts.push(
      `<rect x="${map.bounds.width + 24}" y="${y - 13}" width="14" height="14" fill="${alliance.presentation_color}" opacity="${alliance.visible ? '1' : '.35'}"/>`,
    );
    parts.push(
      `<text x="${map.bounds.width + 48}" y="${y}" fill="#e8edf2" font-family="sans-serif" font-size="14">${escapeXml(alliance.display_name)}</text>`,
    );
  });

  const footerY = map.bounds.height + 36;
  parts.push(
    `<text x="20" y="${footerY}" fill="#f5d88a" font-family="sans-serif" font-size="20" font-weight="700">${escapeXml(metadata.title)}</text>`,
  );
  parts.push(
    `<text x="20" y="${footerY + 30}" fill="#b9c4cc" font-family="sans-serif" font-size="13">Map: ${escapeXml(metadata.mapProfile)} · observed ${escapeXml(metadata.observedAt)} · ${escapeXml(metadata.confidence)}</text>`,
  );
  parts.push(
    `<text x="20" y="${footerY + 54}" fill="#87939c" font-family="sans-serif" font-size="12">Exported ${escapeXml(metadata.exportedAt)} · coordinates are planning data, not an official Century Games map claim.</text>`,
  );
  parts.push('</svg>');
  return parts.join('');
}

export function downloadText(filename: string, content: string, type: string): void {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

export async function downloadPngFromSvg(
  filename: string,
  svg: string,
  width = 1800,
): Promise<void> {
  const blob = new Blob([svg], { type: 'image/svg+xml' });
  const url = URL.createObjectURL(blob);
  try {
    const image = new Image();
    await new Promise<void>((resolve, reject) => {
      image.onload = () => resolve();
      image.onerror = () => reject(new Error('Unable to render territory export.'));
      image.src = url;
    });
    const ratio = image.naturalHeight / image.naturalWidth;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = Math.max(1, Math.round(width * ratio));
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Unable to render territory export.');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    const png = canvas.toDataURL('image/png');
    const anchor = document.createElement('a');
    anchor.href = png;
    anchor.download = filename;
    anchor.click();
  } finally {
    URL.revokeObjectURL(url);
  }
}
