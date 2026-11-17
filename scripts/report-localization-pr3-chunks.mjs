import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
const [baselinePath, currentPath] = process.argv.slice(2);
if (!baselinePath || !currentPath) throw new Error('Provide PR1 and PR3 vite-chunks.json paths');
const baseline = JSON.parse(readFileSync(baselinePath, 'utf8'));
const current = JSON.parse(readFileSync(currentPath, 'utf8'));
if (baseline.status !== 'captured' || current.status !== 'captured') throw new Error('Vite chunk evidence missing from a production build');
const bySource = (build) => new Map(build.chunks.filter((x) => x.source).map((x) => [x.source, x]));
const before = bySource(baseline), after = bySource(current);
const changes = [];
for (const source of new Set([...before.keys(), ...after.keys()])) {
  const a = before.get(source), b = after.get(source);
  if (!a && !b) continue;
  if (!a || !b || a.bytes !== b.bytes) changes.push({ source, beforeBytes: a?.bytes ?? null, afterBytes: b?.bytes ?? null, deltaBytes: a && b ? b.bytes - a.bytes : null, kind: !a ? 'added' : !b ? 'removed' : 'changed' });
}
changes.sort((a, b) => a.source.localeCompare(b.source));
const performanceBudget = JSON.parse(readFileSync('performance-budgets.json', 'utf8')).largestPageChunkBytes;
const beforePage = baseline.largestPage;
const afterPage = current.largestPage;
assert.ok(beforePage && afterPage, 'largest page source is required');
assert.equal(beforePage.source, 'resources/js/pages/Operations/Events/Manage.vue', 'confirm known baseline largest page');
assert.equal(afterPage.source, beforePage.source, 'a different largest page requires fresh performance review');
assert.equal(afterPage.bytes, beforePage.bytes, 'Recipient/Transfer overlay consolidation must not increase the largest page chunk');
assert.ok(afterPage.bytes <= performanceBudget, 'largest-page budget exceeded');
const largestPageHeadroomBytes = performanceBudget - afterPage.bytes;
const englishDomainChanges = changes.filter((item) =>
  item.source.startsWith('resources/js/localization/messages/') && item.source.endsWith('/en.ts')
);

// A source-mapped localization migration can increase domain chunks while deleting
// equivalent separately emitted overlays. Measure both, not just the grown files.
const relocatedOverlaySources = new Set([
  'resources/js/localization/communications-recipient-delivery-labels.ts',
  'resources/js/localization/transfer-evidence-labels.ts',
]);
const sum = (items) => items.reduce((total, item) => total + item.bytes, 0);
const localizationBytes = (build) => sum(build.chunks.filter((item) =>
  item.isLocale || relocatedOverlaySources.has(item.source)
));
const originalLocalizationBytes = localizationBytes(baseline);
const currentLocalizationBytes = localizationBytes(current);
const localizationNetDeltaBytes = currentLocalizationBytes - originalLocalizationBytes;
const baselineEmittedJsBytes = sum(baseline.chunks);
const currentEmittedJsBytes = sum(current.chunks);
const netEmittedJsDeltaBytes = currentEmittedJsBytes - baselineEmittedJsBytes;
assert.ok(currentLocalizationBytes > 0 && originalLocalizationBytes > 0, 'no measured localization JavaScript');

const result = {
  baselinePath, currentPath,
  originalLargestPage: baseline.largestPage,
  currentLargestPage: current.largestPage,
  originalChunks: baseline.chunks.length,
  currentChunks: current.chunks.length,
  changedChunks: changes,
  largestPageBudgetBytes: performanceBudget,
  largestPageHeadroomBytes,
  englishDomainChanges,
  originalLocalizationBytes,
  currentLocalizationBytes,
  localizationNetDeltaBytes,
  baselineEmittedJsBytes,
  currentEmittedJsBytes,
  netEmittedJsDeltaBytes,
};
writeFileSync('.translation-pr3-current/vite-chunk-comparison.json', JSON.stringify(result, null, 2) + '\n');
console.log('PR3 CHUNK DELTAS: ' + JSON.stringify({
  total: changes.length,
  largestPageSource: afterPage.source,
  largestBefore: beforePage.bytes,
  largestAfter: afterPage.bytes,
  hardLargestPageBudgetBytes: performanceBudget,
  remainingHeadroomBytes: largestPageHeadroomBytes,
  englishDomainChanges,
  originalLocalizationBytes,
  currentLocalizationBytes,
  localizationNetDeltaBytes,
  baselineEmittedJsBytes,
  currentEmittedJsBytes,
  netEmittedJsDeltaBytes,
  allChangedChunks: changes,
}));
