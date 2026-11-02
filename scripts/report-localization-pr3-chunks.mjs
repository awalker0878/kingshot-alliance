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
const result = {
  baselinePath, currentPath,
  originalLargestPage: baseline.largestPage,
  currentLargestPage: current.largestPage,
  originalChunks: baseline.chunks.length,
  currentChunks: current.chunks.length,
  changedChunks: changes,
};
writeFileSync('.translation-pr3-current/vite-chunk-comparison.json', JSON.stringify(result, null, 2) + '\n');
console.log('PR3 CHUNK DELTAS: ' + JSON.stringify({ total: changes.length, largestBefore: baseline.largestPage?.bytes, largestAfter: current.largestPage?.bytes, transfers: changes.filter((x) => x.source?.includes('localization/messages/transfers/')) }));
