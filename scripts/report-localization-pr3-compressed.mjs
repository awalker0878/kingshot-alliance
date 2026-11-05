import assert from 'node:assert/strict';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const [oldRoot, newRoot, oldManifestPath, newManifestPath] = process.argv.slice(2);
if (!oldRoot || !newRoot || !oldManifestPath || !newManifestPath)
  throw new Error('Usage: node scripts/report-localization-pr3-compressed.mjs OLD_BUILD_ROOT NEW_BUILD_ROOT ORIGINAL_VITE_CHUNKS CURRENT_VITE_CHUNKS');
const oldManifest = JSON.parse(readFileSync(path.join(oldRoot, 'public/build/manifest.json'), 'utf8'));
const newManifest = JSON.parse(readFileSync(path.join(newRoot, 'public/build/manifest.json'), 'utf8'));
const originalBytes = JSON.parse(readFileSync(oldManifestPath, 'utf8')).chunks;
const currentBytes = JSON.parse(readFileSync(newManifestPath, 'utf8')).chunks;
const sourceChunk = (root, manifest, expected, source) => {
  const match = Object.values(manifest).filter((x) => x.src === source && x.file?.endsWith('.js'));
  assert.equal(match.length, 1, 'no unique built JavaScript chunk for ' + source);
  const buffer = readFileSync(path.join(root, 'public/build', match[0].file));
  const pinned = expected.filter((x) => x.source === source);
  assert.equal(pinned.length, 1, 'missing pinned Vite chunk for ' + source);
  assert.equal(buffer.length, pinned[0].bytes, 'rebuild differs in size from recorded source-mapped production chunk: ' + source);
  return {
    source,
    file: match[0].file,
    raw: buffer.length,
    gzip: gzipSync(buffer, { level: 9 }).length,
    brotli: brotliCompressSync(buffer, { params: { [constants.BROTLI_PARAM_QUALITY]: 11 } }).length,
  };
};
const scenarios = [
  { page: 'Accounts/Notifications/Index', domain: 'account', overlay: 'communications-recipient-delivery-labels.ts' },
  { page: 'Kingdom/Transfer/Index', domain: 'transfers', overlay: 'transfer-evidence-labels.ts' },
];
const sizes = ['raw', 'gzip', 'brotli'];
const rows = scenarios.map((scenario) => {
  const base = 'resources/js/localization/';
  const englishSource = base + 'messages/' + scenario.domain + '/en.ts';
  const overlaySource = base + scenario.overlay;
  const before = [
    sourceChunk(oldRoot, oldManifest, originalBytes, englishSource),
    sourceChunk(oldRoot, oldManifest, originalBytes, overlaySource),
  ];
  const after = [sourceChunk(newRoot, newManifest, currentBytes, englishSource)];
  const sum = (chunks, metric) => chunks.reduce((total, x) => total + x[metric], 0);
  const totals = Object.fromEntries(sizes.map((metric) => [metric, {
    before: sum(before, metric),
    after: sum(after, metric),
    change: sum(after, metric) - sum(before, metric),
  }]));
  return { entryPage: scenario.page, coldEnglishFallbackAndMandatoryOverlay: before,
    currentCanonicalEnglishDomain: after, totals };
});
const manage = 'resources/js/pages/Operations/Events/Manage.vue';
const oldPage = sourceChunk(oldRoot, oldManifest, originalBytes, manage);
const newPage = sourceChunk(newRoot, newManifest, currentBytes, manage);
assert.equal(newPage.raw, 73089);
assert.equal(newPage.raw, oldPage.raw, 'Manage page must not grow');
const report = {
  method: 'fresh npm Vite production builds with identical locked dependencies, gzip level 9 and Brotli quality 11 on actual emitted asset bytes; each independent JS file compressed separately',
  baselineCommit: 'a5151e717020cfe00a32aeddcca6279f9c7042fe',
  heads: rows,
  manageBefore: oldPage, manageAfter: newPage,
  limitations: [
    'Compression is deterministic offline gzip/Brotli, not observed CDN/server Content-Encoding or browser transfer.',
    'Figures exclude other shared JS assets, caching, HTTP framing and download timing; preexisting overlay loaded for every first English domain load.',
  ],
};
writeFileSync('.translation-pr3-current/compressed-first-entry-costs.json', JSON.stringify(report, null, 2) + '\n');
console.log('PR3 COMPRESSED COLD DOMAIN COSTS: ' + JSON.stringify({ rows: rows.map((x) => ({ page: x.entryPage, ...x.totals })), manageBefore: { raw: oldPage.raw, gzip: oldPage.gzip, brotli: oldPage.brotli }, manageAfter: { raw: newPage.raw, gzip: newPage.gzip, brotli: newPage.brotli } }));
