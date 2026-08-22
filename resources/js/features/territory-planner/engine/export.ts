import type { MapData, PlanAlliance, PlanObject } from './types';

export function buildSvg(map: MapData, alliances: PlanAlliance[], objects: PlanObject[], title: string): string {
  const visible = new Set(alliances.filter((alliance) => alliance.visible).map((alliance) => alliance.key));
  const colors = new Map(alliances.map((alliance) => [alliance.key, alliance.presentation_color]));
  const parts: string[] = [];
  const escape = (value: string) => value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[character] ?? character);
  parts.push(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${map.bounds.width} ${map.bounds.height}" role="img" aria-label="${escape(title)}">`);
  parts.push('<rect width="100%" height="100%" fill="#111827"/>');
  for (const structure of map.structures) {
    parts.push(`<rect x="${structure.x}" y="${map.bounds.height - structure.y - structure.size}" width="${structure.size}" height="${structure.size}" fill="#7c6f64" opacity="0.85"/>`);
  }
  for (const object of objects) {
    if (!visible.has(object.alliance_key)) continue;
    const definition = map.object_types[object.type];
    if (!definition) continue;
    const color = colors.get(object.alliance_key) ?? '#4da3ff';
    if (definition.coverage > 0) {
      parts.push(`<rect x="${object.x - definition.coverage}" y="${map.bounds.height - object.y - definition.size - definition.coverage}" width="${definition.size + definition.coverage * 2}" height="${definition.size + definition.coverage * 2}" fill="${color}" opacity="0.12"/>`);
    }
    parts.push(`<rect x="${object.x}" y="${map.bounds.height - object.y - definition.size}" width="${definition.size}" height="${definition.size}" fill="${color}" stroke="#fff" stroke-width="0.35"/>`);
  }
  parts.push(`<text x="20" y="32" fill="#fff" font-family="sans-serif" font-size="18">${escape(title)}</text>`);
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
