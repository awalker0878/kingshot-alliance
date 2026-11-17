import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { stableReviewIdentity } from './localization/stable-review-identity.mjs';

const root = process.argv[2] ?? '.translation-pr1-baseline';
const input = readFileSync(path.join(root, 'tier1a-v2-exploratory.json'));
const bridge = readFileSync(path.join(root, 'server-php-stable-keys.json'));
const report = JSON.parse(input);
const php = JSON.parse(bridge);
const candidates = report.candidates.map((entry) => stableReviewIdentity(entry, php));
assert.equal(candidates.length, 613);
assert.equal(
  new Set(candidates.map((entry) => JSON.stringify([entry.catalogue, entry.domain, entry.key])))
    .size,
  613,
);
const hash = (bytes) => createHash('sha256').update(bytes).digest('hex');
const output = {
  version: 1,
  status: 'EXPLORATORY_REQUIRES_ADJUDICATION',
  sourceRef: report.pinnedBaseRef,
  classifierSha256: hash(readFileSync('scripts/report-localization-tier1a-v2.mjs')),
  inputSha256: hash(input),
  identityBridgeSha256: hash(bridge),
  priorCandidates: report.priorStrictCandidates,
  candidates,
  nativeApprovals: 0,
  approvedReleaseScope: false,
};
writeFileSync(
  path.join(root, 'tier1a-v2-stable-identities.json'),
  JSON.stringify(output, null, 2) + '\n',
);
console.log('Stable candidate identities: 613; original 511 preserved; release scope unapproved.');
