import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { gzipSync, brotliCompressSync, constants } from 'node:zlib';

const [beforeDirectory, afterDirectory = 'public/build'] = process.argv.slice(2);
assert.ok(beforeDirectory, 'Usage: report-localization-overlay-costs.mjs BEFORE_BUILD [AFTER_BUILD]');
const inventory = JSON.parse(readFileSync('.translation-pr3-current/remaining-overlay-inventory.json', 'utf8'));
const readManifest = directory => JSON.parse(readFileSync(path.join(directory, 'manifest.json'), 'utf8'));
const before = readManifest(beforeDirectory), after = readManifest(afterDirectory);
const base = 'resources/js/localization/';
function cost(directory, manifest, sources) {
  const included = new Set();
  function visit(source) {
    const entry = manifest[source];
    assert.ok(entry, 'Missing built source: ' + source);
    if (included.has(source)) return;
    included.add(source);
    for (const imported of entry.imports ?? []) visit(imported);
  }
  for (const source of sources) visit(source);
  const assets = [...new Set([...included].map(source => manifest[source].file))];
  const sizes = assets.map(file => {
    const bytes = readFileSync(path.join(directory, file));
    return { file, raw: bytes.length, gzip: gzipSync(bytes, { level: 9 }).length,
      brotli: brotliCompressSync(bytes, { params: { [constants.BROTLI_PARAM_QUALITY]: 11 } }).length };
  });
  return { assets, ...Object.fromEntries(['raw', 'gzip', 'brotli'].map(metric => [metric, sizes.reduce((sum, item) => sum + item[metric], 0)])) };
}
const domains = [...new Set(inventory.scopes.map(scope => scope.domain))];
const rows = domains.flatMap(domain => ['en', 'fr', 'ar'].map(locale => {
  const native = [`${base}messages/${domain}/en.ts`];
  if (locale !== 'en') native.push(`${base}messages/${domain}/${locale}.ts`);
  const overlays = inventory.scopes.filter(scope => scope.domain === domain).map(scope => base + scope.overlay + '.ts');
  const previous = cost(beforeDirectory, before, [...native, ...overlays]);
  const current = cost(afterDirectory, after, native);
  return { domain, locale, before: previous, after: current,
    delta: Object.fromEntries(['raw', 'gzip', 'brotli'].map(metric => [metric, current[metric] - previous[metric]])) };
}));
const sourceBytes = (directory, manifest, source) => readFileSync(path.join(directory, manifest[source].file)).length;
const manage = 'resources/js/pages/Operations/Events/Manage.vue';
const app = 'resources/js/app.ts';
const report = {
  baseline: inventory.sourceRevision,
  method: 'Actual Vite builds; gzip level 9 and Brotli quality 11 per emitted asset, with static import closure and deduplication. Domain costs include English fallback; page/app are raw emitted chunks. This is not measured browser transfer or CDN encoding.',
  rows,
  manage: { before: sourceBytes(beforeDirectory, before, manage), after: sourceBytes(afterDirectory, after, manage), budget: 73728 },
  app: { before: sourceBytes(beforeDirectory, before, app), after: sourceBytes(afterDirectory, after, app) },
};
assert.ok(report.manage.after <= report.manage.budget, 'Manage page budget exceeded');
writeFileSync('.translation-pr3-current/remaining-overlay-costs.json', JSON.stringify(report, null, 2) + '\n');
console.log(JSON.stringify({ manage: report.manage, app: report.app, english: rows.filter(row => row.locale === 'en').map(({ domain, before, after, delta }) => ({ domain, before: before.raw, after: after.raw, delta })) }, null, 2));
