import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const samplePath = process.argv[2] ?? '.translation-pr1-baseline/risk-unflagged-samples.json';
const outputDirectory = process.argv[3] ?? '.translation-pr1-baseline';
const pinnedSampleSha = '8c77a3d7844889e99427e6d6cb2f2a47b54e526791f02461d97ef9772af35de3';
const data = readFileSync(samplePath);
assert.equal(createHash('sha256').update(data).digest('hex'), pinnedSampleSha, 'sample input drift');
const samples = JSON.parse(data.toString('utf8')).samples;
const unique = new Map();
let placements = 0;
for (const [locale, rows] of Object.entries(samples)) {
  assert.equal(rows.length, 32);
  for (const item of rows) {
    placements++;
    const sourceId = [item.catalogue, item.domain, item.key].join(':');
    const prior = unique.get(sourceId);
    if (prior) assert.equal(prior.source, item.source, 'same identity has inconsistent English source');
    else unique.set(sourceId, {
      sourceId,
      catalogue: item.catalogue,
      domain: item.domain,
      key: item.key,
      englishSource: item.source,
    });
  }
}
assert.equal(placements, 512);
assert.equal(unique.size, 492);
const seed = 'blind-english-risk-review-v1:' + pinnedSampleSha;
const token = (id) => createHash('sha256').update(seed + ':' + id).digest('hex');
const shuffled = [...unique.values()].sort((a, b) => token(a.sourceId).localeCompare(token(b.sourceId)));
const queue = shuffled.map((entry, index) => ({
  reviewId: 'E-' + String(index + 1).padStart(3, '0'),
  sourceId: entry.sourceId,
  englishSource: entry.englishSource,
  tierDecision: '',
  riskCategories: '',
  needsContext: '',
  rationale: '',
  reviewer: '',
  reviewedAtUtc: '',
}));
const escape = (v) => '"' + String(v ?? '').replaceAll('"', '""') + '"';
const columns = Object.keys(queue[0]);
const csv = [columns.map(escape).join(','), ...queue.map((r) => columns.map((k) => escape(r[k])).join(','))].join('\n') + '\n';
mkdirSync(outputDirectory, { recursive: true });
writeFileSync(outputDirectory + '/english-source-blind-queue.csv', csv);
writeFileSync(outputDirectory + '/english-source-blind-queue-metadata.json', JSON.stringify({
  inputSha256: pinnedSampleSha,
  sourceIdentity: 'catalogue:domain:key',
  uniqueSources: unique.size,
  localePlacements: placements,
  randomization: 'Deterministic SHA-256 blinded ordering by predeclared seed',
  rubric: 'docs/frontend/localization-tier1-english-risk-rubric.md',
  warnings: [
    'Reviewer-facing CSV deliberately excludes locale and any v1/v2 classifier match, risk reasons, translated text, or sample match priority.',
    'The English source risk review is distinct from native translation QA.',
    'Do not deblind or revise the classifier until all source decisions and adjudications are recorded.',
  ],
}, null, 2) + '\n');
console.log('TIER1 BLIND SOURCE REVIEW: ' + JSON.stringify({
  uniqueKeys: queue.length,
  placements,
  reviewerColumns: columns,
  firstReviewIds: queue.slice(0, 4).map((r) => r.reviewId),
  flagsExposed: false,
}));
